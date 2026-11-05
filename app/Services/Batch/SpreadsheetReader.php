<?php

namespace App\Services\Batch;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads the first sheet of a CSV, XLSX, XLS or ODS file into a header row
 * and data rows of trimmed strings. Blank rows are skipped; each row keeps
 * its spreadsheet line number for error reports.
 */
class SpreadsheetReader
{
    public const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls', 'ods'];

    /**
     * @return array{headers: array<int, string>, rows: array<int, array{line: int, cells: array<int, string>}>}
     *
     * @throws RuntimeException when the file can't be read or has more than $maxRows rows
     */
    public function read(string $path, string $extension, int $maxRows): array
    {
        $extension = strtolower($extension);
        $lines = in_array($extension, ['csv', 'txt'], true) ? $this->csv($path, $maxRows) : $this->spreadsheet($path, $extension, $maxRows);

        $headers = null;
        $rows = [];
        foreach ($lines as $line => $cells) {
            $cells = array_map(fn ($v) => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v) ?? ''), $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            if ($headers === null) {
                $headers = array_map(fn ($h) => mb_substr($h, 0, 80), $cells);

                continue;
            }
            if (count($rows) >= $maxRows) {
                throw new RuntimeException("The file has more than {$maxRows} rows. Split it into smaller files.");
            }
            $rows[] = ['line' => $line, 'cells' => $cells];
        }

        if (! $headers) {
            throw new RuntimeException('The file is empty. The first row must hold the column names.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return iterable<int, array<int, string>> line number => cells */
    private function csv(string $path, int $maxRows): iterable
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            // Excel on Windows saves CSV as Windows-1252.
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t", '|'])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $lines = [];
        $line = 0;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            $lines[$line] = $cells;
            if ($line > $maxRows + 50) {
                break; // enough to report "too many rows" without reading a huge file
            }
        }
        fclose($handle);

        return $lines;
    }

    /** @return iterable<int, array<int, string>> */
    private function spreadsheet(string $path, string $extension, int $maxRows): iterable
    {
        try {
            $reader = IOFactory::createReader(match ($extension) {
                'xlsx' => 'Xlsx', 'xls' => 'Xls', 'ods' => 'Ods',
                default => throw new RuntimeException('Unsupported file type.'),
            });
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $book = $reader->load($path);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('This spreadsheet could not be read. Save it again as .xlsx or .csv and retry.');
        }

        $sheet = $book->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        if ($highestRow > $maxRows + 200) {
            throw new RuntimeException("The file has more than {$maxRows} rows. Split it into smaller files.");
        }
        $highestColumn = $sheet->getHighestDataColumn();
        $lines = [];
        foreach ($sheet->rangeToArray('A1:'.$highestColumn.$highestRow, '', true, true, false) as $i => $cells) {
            $lines[$i + 1] = array_map(fn ($v) => is_float($v) && floor($v) === $v && abs($v) < 1e15 ? number_format($v, 0, '.', '') : (string) $v, $cells);
        }
        $book->disconnectWorksheets();

        return $lines;
    }
}
