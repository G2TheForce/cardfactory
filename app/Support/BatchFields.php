<?php

namespace App\Support;

use App\Http\Requests\Customers\CustomerRequest;
use App\Services\DigitalProfileService;
use Illuminate\Support\Str;

/**
 * Columns the bulk generator understands: label, validation rules and the
 * header names that are mapped automatically (compared case- and
 * punctuation-insensitively).
 */
final class BatchFields
{
    /** @var array<string, array{label: string, aliases: array<int, string>, example: string}> */
    public const FIELDS = [
        'full_name' => ['label' => 'Full name', 'aliases' => ['name', 'fullname', 'customer', 'customername', 'contactname', 'employee'], 'example' => 'Ada Lovelace'],
        'professional_title' => ['label' => 'Professional title', 'aliases' => ['title', 'jobtitle', 'position', 'role'], 'example' => 'Chief Engineer'],
        'designation' => ['label' => 'Designation', 'aliases' => ['credentials', 'postnominals'], 'example' => 'PhD'],
        'department' => ['label' => 'Department', 'aliases' => ['dept', 'team', 'division'], 'example' => 'Research'],
        'mobile' => ['label' => 'Mobile', 'aliases' => ['phone', 'mobilephone', 'cell', 'cellphone', 'mobilenumber', 'phonenumber'], 'example' => '+44 7700 900123'],
        'whatsapp' => ['label' => 'WhatsApp', 'aliases' => ['whatsappnumber'], 'example' => '+44 7700 900123'],
        'office_phone' => ['label' => 'Office phone', 'aliases' => ['officephone', 'workphone', 'telephone', 'tel', 'landline'], 'example' => '+44 20 7946 0000'],
        'email' => ['label' => 'Email', 'aliases' => ['emailaddress', 'mail', 'workemail'], 'example' => 'ada@example.com'],
        'website' => ['label' => 'Website', 'aliases' => ['web', 'url', 'site', 'homepage'], 'example' => 'https://example.com'],
        'company_name' => ['label' => 'Company', 'aliases' => ['company', 'organisation', 'organization', 'employer', 'business'], 'example' => 'Analytical Engines Ltd'],
        'company_website' => ['label' => 'Company website', 'aliases' => ['companyurl', 'companysite'], 'example' => 'https://engines.example.com'],
        'office_address' => ['label' => 'Office address', 'aliases' => ['address', 'street', 'streetaddress', 'officeaddress'], 'example' => '12 St James Square'],
        'city' => ['label' => 'City', 'aliases' => ['town'], 'example' => 'London'],
        'province' => ['label' => 'Province / state', 'aliases' => ['state', 'region', 'county'], 'example' => 'Greater London'],
        'country' => ['label' => 'Country', 'aliases' => [], 'example' => 'United Kingdom'],
        'linkedin' => ['label' => 'LinkedIn', 'aliases' => ['linkedinurl'], 'example' => 'https://www.linkedin.com/in/ada'],
        'facebook' => ['label' => 'Facebook', 'aliases' => [], 'example' => ''],
        'instagram' => ['label' => 'Instagram', 'aliases' => [], 'example' => ''],
        'x' => ['label' => 'X (Twitter)', 'aliases' => ['twitter', 'xtwitter'], 'example' => ''],
        'tiktok' => ['label' => 'TikTok', 'aliases' => [], 'example' => ''],
        'youtube' => ['label' => 'YouTube', 'aliases' => [], 'example' => ''],
        'github' => ['label' => 'GitHub', 'aliases' => [], 'example' => ''],
        'introduction' => ['label' => 'Introduction', 'aliases' => ['bio', 'about', 'summary'], 'example' => 'Mathematician and writer.'],
        'booking_url' => ['label' => 'Booking link', 'aliases' => ['booking', 'calendar', 'appointment'], 'example' => ''],
        'portfolio_url' => ['label' => 'Portfolio link', 'aliases' => ['portfolio'], 'example' => ''],
        'profile_slug' => ['label' => 'Profile address', 'aliases' => ['slug', 'profileurl', 'username', 'handle'], 'example' => 'ada-lovelace'],
    ];

    /** Validation rules for one mapped row (same rules as the customer form). */
    public static function rules(): array
    {
        $url = CustomerRequest::URL_RULE;
        $phone = CustomerRequest::PHONE_RULE;
        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];

        $rules = [
            'full_name' => ['required', 'string', 'max:120'],
            'professional_title' => $text(120), 'designation' => $text(120), 'department' => $text(120),
            'mobile' => $phone, 'whatsapp' => $phone, 'office_phone' => $phone,
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => $url, 'company_name' => $text(120), 'company_website' => $url,
            'office_address' => $text(255), 'city' => $text(100), 'province' => $text(100), 'country' => $text(100),
            'introduction' => $text(2000), 'booking_url' => $url, 'portfolio_url' => $url,
            'profile_slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:'.DigitalProfileService::SLUG_PATTERN],
        ];
        foreach (array_keys(CustomerFields::SOCIAL) as $network) {
            $rules[$network] = $url;
        }

        return $rules;
    }

    public static function messages(): array
    {
        return [
            '*.regex' => CustomerRequest::PHONE_MESSAGE,
            'profile_slug.regex' => 'Use lowercase letters, numbers and dashes (3–60 characters).',
            '*.url' => 'Enter a full link starting with https://',
        ];
    }

    public static function normaliseHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($header)));
    }

    /**
     * Guess field => column index from the header row.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    public static function guessMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $h = self::normaliseHeader((string) $header);
            if ($h === '') {
                continue;
            }
            foreach (self::FIELDS as $field => $meta) {
                if (isset($mapping[$field])) {
                    continue;
                }
                $names = array_merge([self::normaliseHeader($field), self::normaliseHeader($meta['label'])], $meta['aliases']);
                if (in_array($h, $names, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }
}
