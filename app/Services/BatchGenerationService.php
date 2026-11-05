<?php

namespace App\Services;

use App\Jobs\ProcessCustomerImport;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\CustomerImport;
use App\Models\User;
use App\Services\Batch\SpreadsheetReader;
use App\Support\BatchFields;
use App\Support\CustomerFields;
use App\Support\ProfileThemes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Bulk card generation from a spreadsheet:
 *  1. upload  – the file is stored privately and its header row read;
 *  2. map     – spreadsheet columns are matched to customer fields;
 *  3. check   – every row is validated with the customer form's rules,
 *               plus duplicate e-mails and profile addresses;
 *  4. generate (queued) – per valid row: customer, card design from the
 *               chosen theme or template design, and optionally a digital
 *               profile with its own public URL and QR code.
 * The uploaded file is deleted once generation finishes; the error report
 * keeps row numbers and messages only, not the submitted values.
 */
class BatchGenerationService
{
    public const DISK = 'local';

    /** Rows shown on the preview page. */
    public const PREVIEW_ROWS = 25;

    /** Error report entries kept per import. */
    public const MAX_ERRORS = 1000;

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly CustomerService $customers,
        private readonly CardDesignService $designs,
        private readonly DigitalProfileService $profiles,
        private readonly ActivityLogger $logger,
    ) {}

    /** @throws ValidationException */
    public function upload(User $user, UploadedFile $file): CustomerImport
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $ulid = (string) Str::ulid();
        $path = Storage::disk(self::DISK)->putFileAs('imports/'.$ulid, $file, 'source.'.$ext);

        try {
            $sheet = $this->reader->read(Storage::disk(self::DISK)->path($path), $ext, $this->maxRows());
        } catch (RuntimeException $e) {
            Storage::disk(self::DISK)->deleteDirectory('imports/'.$ulid);
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        if (count($sheet['rows']) === 0) {
            Storage::disk(self::DISK)->deleteDirectory('imports/'.$ulid);
            throw ValidationException::withMessages(['file' => 'The file has a header row but no data rows.']);
        }

        $import = new CustomerImport([
            'user_id' => $user->id,
            'original_filename' => Str::limit($file->getClientOriginalName(), 200, ''),
            'file_path' => $path,
            'headers' => $sheet['headers'],
            'column_mapping' => BatchFields::guessMapping($sheet['headers']),
            'options' => $this->defaultOptions(),
            'status' => 'uploaded',
            'total_rows' => count($sheet['rows']),
        ]);
        $import->ulid = $ulid;
        $import->save();
        $this->logger->log('import.uploaded', $import, 'Uploaded "'.$import->original_filename.'" for bulk generation ('.$import->total_rows.' rows)');

        return $import;
    }

    public function defaultOptions(): array
    {
        return ['source' => 'theme', 'create_profiles' => true, 'profile_theme' => 'classic', 'skip_existing' => true];
    }

    /**
     * Save the column mapping and generation options, then check every row.
     *
     * @param  array<string, int|string|null>  $mapping  field => column index
     */
    public function configure(CustomerImport $import, array $mapping, array $options, ?CardTheme $theme, ?CardDesign $template): CustomerImport
    {
        $columns = count($import->headers ?? []);
        $clean = [];
        foreach ($mapping as $field => $index) {
            if (isset(BatchFields::FIELDS[$field]) && $index !== null && $index !== '' && (int) $index >= 0 && (int) $index < $columns) {
                $clean[$field] = (int) $index;
            }
        }
        if (! isset($clean['full_name'])) {
            throw ValidationException::withMessages(['mapping.full_name' => 'Choose the column that holds the full name.']);
        }

        $import->fill([
            'column_mapping' => $clean,
            'card_theme_id' => $theme?->id,
            'card_design_id' => $template?->id,
            'options' => [
                'source' => $template ? 'design' : 'theme',
                'create_profiles' => (bool) ($options['create_profiles'] ?? false),
                'profile_theme' => isset(ProfileThemes::THEMES[$options['profile_theme'] ?? '']) ? $options['profile_theme'] : 'classic',
                'skip_existing' => (bool) ($options['skip_existing'] ?? false),
            ],
        ]);

        $result = $this->check($import);
        $import->fill([
            'status' => 'validated',
            'valid_rows' => count($result['valid']),
            'failed_rows' => $result['failed'],
            'errors' => array_slice($result['errors'], 0, self::MAX_ERRORS),
        ])->save();

        return $import;
    }

    /**
     * Validate all rows against the current mapping.
     *
     * @return array{valid: array<int, array<string, mixed>>, errors: array<int, array{line: int, field: ?string, column: ?string, message: string, level: string}>, failed: int}
     */
    public function check(CustomerImport $import): array
    {
        $rows = $this->rows($import);
        $headers = $import->headers ?? [];
        $mapping = $import->column_mapping ?? [];
        $options = $import->options ?? [];
        $valid = $errors = [];
        $failed = 0;
        $seenEmails = $seenSlugs = [];

        $existing = [];
        if (! empty($options['skip_existing'])) {
            $emails = collect($rows)->pluck('data.email')->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values();
            foreach ($emails->chunk(500) as $chunk) {
                Customer::whereIn(DB::raw('LOWER(email)'), $chunk->all())->pluck('email')
                    ->each(function ($e) use (&$existing) {
                        $existing[mb_strtolower($e)] = true;
                    });
            }
        }

        foreach ($rows as $row) {
            $line = $row['line'];
            $data = $row['data'];
            $rowErrors = [];

            $validator = Validator::make($data, BatchFields::rules(), BatchFields::messages(), collect(BatchFields::FIELDS)->map(fn ($f) => $f['label'])->all());
            foreach ($validator->errors()->messages() as $field => $messages) {
                $rowErrors[] = ['field' => $field, 'message' => $messages[0]];
            }

            $email = $data['email'] ? mb_strtolower($data['email']) : null;
            if ($email && isset($existing[$email])) {
                $errors[] = $this->error($line, 'email', $headers, $mapping, 'A customer with this email already exists, so the row was skipped.', 'skipped');
                $failed++;

                continue;
            }
            if ($email && isset($seenEmails[$email])) {
                $rowErrors[] = ['field' => 'email', 'message' => 'Same email as row '.$seenEmails[$email].'.'];
            }

            $slug = $data['profile_slug'];
            if (! empty($options['create_profiles']) && $slug && ! $validator->errors()->has('profile_slug')) {
                if (isset($seenSlugs[$slug])) {
                    $rowErrors[] = ['field' => 'profile_slug', 'message' => 'Same profile address as row '.$seenSlugs[$slug].'.'];
                } elseif (! $this->profiles->slugAvailable($slug)) {
                    $rowErrors[] = ['field' => 'profile_slug', 'message' => 'This profile address is taken or not allowed.'];
                }
            }

            if ($rowErrors) {
                $failed++;
                foreach ($rowErrors as $e) {
                    $errors[] = $this->error($line, $e['field'], $headers, $mapping, $e['message']);
                }

                continue;
            }

            if ($email) {
                $seenEmails[$email] = $line;
            }
            if ($slug) {
                $seenSlugs[$slug] = $line;
            }
            $valid[$line] = $data;
        }

        return ['valid' => $valid, 'errors' => $errors, 'failed' => $failed];
    }

    /**
     * Mapped rows from the stored file.
     *
     * @return array<int, array{line: int, data: array<string, ?string>}>
     */
    public function rows(CustomerImport $import): array
    {
        $sheet = $this->sheet($import);
        $mapping = $import->column_mapping ?? [];

        return array_map(function ($row) use ($mapping) {
            $data = [];
            foreach (array_keys(BatchFields::FIELDS) as $field) {
                $value = isset($mapping[$field]) ? trim((string) ($row['cells'][$mapping[$field]] ?? '')) : '';
                $data[$field] = $value === '' ? null : $this->normaliseValue($field, $value);
            }

            return ['line' => $row['line'], 'data' => $data];
        }, $sheet['rows']);
    }

    /** The stored file's header and raw rows. */
    public function sheet(CustomerImport $import): array
    {
        if (! $import->file_path || ! Storage::disk(self::DISK)->exists($import->file_path)) {
            throw new RuntimeException('The uploaded file is no longer available. Upload it again.');
        }

        return $this->reader->read(Storage::disk(self::DISK)->path($import->file_path), pathinfo($import->file_path, PATHINFO_EXTENSION), $this->maxRows());
    }

    public function start(CustomerImport $import, User $user): void
    {
        if ($import->status !== 'validated' || $import->valid_rows === 0) {
            throw ValidationException::withMessages(['import' => 'Check the file first. There are no valid rows to generate.']);
        }
        $import->update(['status' => 'processing', 'processed_rows' => 0, 'generated_rows' => 0]);
        $this->logger->log('import.started', $import, 'Started bulk generation of '.$import->valid_rows.' cards from "'.$import->original_filename.'"', [], $user->id);

        ProcessCustomerImport::dispatch($import);
    }

    /** Runs in the queue: create customers, designs and profiles for every valid row. */
    public function process(CustomerImport $import): void
    {
        $actor = $import->user;
        try {
            $result = $this->check($import);
        } catch (\Throwable $e) {
            report($e);
            $import->update(['status' => 'failed', 'errors' => [$this->error(0, null, [], [], $e->getMessage())]]);

            return;
        }

        $theme = $import->theme;
        $template = $import->templateDesign;
        if (! $theme && ! $template) {
            $import->update(['status' => 'failed', 'errors' => [$this->error(0, null, [], [], 'The selected theme or template design was deleted.')]]);

            return;
        }

        $errors = $result['errors'];
        $generated = 0;
        $processed = 0;
        foreach ($result['valid'] as $line => $data) {
            try {
                $this->generateRow($import, $actor, $data, $theme, $template);
                $generated++;
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors[] = $this->error($line, $field === 'slug' ? 'profile_slug' : $field, $import->headers ?? [], $import->column_mapping ?? [], $messages[0]);
                }
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $this->error($line, null, [], [], 'This row could not be generated: '.Str::limit($e->getMessage(), 200));
            }
            $processed++;
            $import->forceFill(['processed_rows' => $processed, 'generated_rows' => $generated])->saveQuietly();
        }

        $path = $import->file_path;
        $import->update([
            'status' => 'completed',
            'generated_rows' => $generated,
            'failed_rows' => $import->total_rows - $generated,
            'errors' => array_slice($errors, 0, self::MAX_ERRORS),
            'file_path' => null,
            'completed_at' => now(),
        ]);
        // The spreadsheet holds personal data that now lives in customer records.
        Storage::disk(self::DISK)->deleteDirectory(dirname((string) $path));
        $this->logger->log('import.completed', $import, 'Generated '.$generated.' cards from "'.$import->original_filename.'"', [], $actor->id);
    }

    /** One row: customer + card design + optional digital profile, all or nothing. */
    private function generateRow(CustomerImport $import, User $actor, array $data, ?CardTheme $theme, ?CardDesign $template): void
    {
        DB::transaction(function () use ($import, $actor, $data, $theme, $template) {
            $input = Arr::only($data, ['full_name', 'professional_title', 'designation', 'department', 'mobile', 'whatsapp',
                'office_phone', 'email', 'website', 'office_address', 'city', 'province', 'country', 'introduction',
                'booking_url', 'portfolio_url', 'company_name', 'company_website']);
            $input['social_links'] = array_filter(Arr::only($data, array_keys(CustomerFields::SOCIAL)));
            if (! $data['company_name']) {
                unset($input['company_name'], $input['company_website']);
            }

            $customer = $this->customers->save(null, $input, $actor);
            $customer->forceFill(['customer_import_id' => $import->id])->save();

            if ($template) {
                $design = $this->designs->duplicate($template, $actor, $customer->full_name.' · '.Str::limit($template->name, 80, ''));
                $design->forceFill(['customer_id' => $customer->id, 'user_id' => $actor->id, 'customer_import_id' => $import->id])->save();
            } else {
                $design = $this->designs->create($actor, ['name' => $customer->full_name.' · '.$theme->name, 'theme' => $theme, 'customer' => $customer]);
                $design->forceFill(['customer_import_id' => $import->id])->save();
            }

            if (! empty($import->options['create_profiles'])) {
                $this->profiles->create($customer, [
                    'slug' => $data['profile_slug'],
                    'profile_theme' => $import->options['profile_theme'] ?? 'classic',
                    'card_design' => $design,
                ], $actor);
            }
        });
    }

    /** Generated customers with their designs and profiles, for the results page and info export. */
    public function results(CustomerImport $import)
    {
        return $import->customers()
            ->with(['company:id,name', 'cardDesigns' => fn ($q) => $q->where('customer_import_id', $import->id),
                'digitalProfiles' => fn ($q) => $q->with('qrCodes')])
            ->orderBy('id');
    }

    /** Rows for the "customer and card information" CSV. */
    public function infoRows(CustomerImport $import): \Generator
    {
        yield ['Customer', 'Title', 'Company', 'Email', 'Mobile', 'Customer ID', 'Design', 'Design ID', 'Profile URL', 'QR URL', 'NFC URL', 'Profile code'];
        foreach ($this->results($import)->lazyById(200) as $customer) {
            $design = $customer->cardDesigns->first();
            $profile = $customer->digitalProfiles->first();
            $urls = $profile ? $this->profiles->urls($profile) : [];
            yield [
                $customer->full_name, $customer->professional_title, $customer->company?->name, $customer->email, $customer->mobile,
                $customer->ulid, $design?->name, $design?->ulid, $urls['public'] ?? '', $urls['qr'] ?? '', $urls['nfc'] ?? '', $profile?->card_identifier,
            ];
        }
    }

    /** Rows for the error report CSV. */
    public function errorRows(CustomerImport $import): \Generator
    {
        yield ['Row', 'Column', 'Field', 'Problem', 'Result'];
        foreach ($import->errors ?? [] as $e) {
            yield [$e['line'] ?: '', $e['column'] ?? '', isset($e['field']) ? (BatchFields::FIELDS[$e['field']]['label'] ?? $e['field']) : '', $e['message'], ($e['level'] ?? 'error') === 'skipped' ? 'Skipped' : 'Not generated'];
        }
    }

    /** Sample spreadsheet with every supported column. */
    public function template(string $format): string
    {
        $headers = array_map(fn ($f) => $f['label'], array_values(BatchFields::FIELDS));
        $example = array_map(fn ($f) => $f['example'], array_values(BatchFields::FIELDS));

        if ($format === 'csv') {
            $out = fopen('php://temp', 'r+');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');
            fputcsv($out, $example, ',', '"', '');
            rewind($out);

            return (string) stream_get_contents($out);
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Customers');
        $sheet->fromArray([$headers, $example], null, 'A1', true);
        // Keep phone numbers as text so leading zeros and + survive.
        $sheet->getStyle('A:Z')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    public function delete(CustomerImport $import, User $actor): void
    {
        if ($import->file_path) {
            Storage::disk(self::DISK)->deleteDirectory(dirname($import->file_path));
        }
        $this->logger->log('import.deleted', $import, 'Deleted bulk import "'.$import->original_filename.'"', [], $actor->id);
        $import->delete();
    }

    public function maxRows(): int
    {
        return max(1, (int) config('smartcard.imports.max_rows', 1000));
    }

    private function normaliseValue(string $field, string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value);
        if ($field === 'profile_slug') {
            return Str::lower($value);
        }
        if ($field === 'email') {
            return Str::lower($value);
        }
        // Spreadsheets often hold "example.com" without a scheme.
        $isUrl = in_array($field, ['website', 'company_website', 'booking_url', 'portfolio_url'], true) || isset(CustomerFields::SOCIAL[$field]);
        if ($isUrl && ! preg_match('~^[a-z][a-z0-9+.-]*://~i', $value) && preg_match('~^[\w-]+(\.[\w-]+)+(/\S*)?$~u', $value)) {
            return 'https://'.$value;
        }

        return $value;
    }

    private function error(int $line, ?string $field, array $headers, array $mapping, string $message, string $level = 'error'): array
    {
        return [
            'line' => $line,
            'field' => $field,
            'column' => $field !== null && isset($mapping[$field]) ? ($headers[$mapping[$field]] ?? null) : null,
            'message' => $message,
            'level' => $level,
        ];
    }
}
