<?php

namespace Tests\Unit;

use App\Services\CardExportService;
use App\Support\BatchFields;
use App\Support\Fonts;
use App\Support\ProfileCodes;
use Tests\TestCase;

class SupportHelpersTest extends TestCase
{
    public function test_spreadsheet_headers_map_to_fields_whatever_their_spelling(): void
    {
        $this->assertSame('emailaddress', BatchFields::normaliseHeader(' E-mail Address '));
        $this->assertSame('societe', BatchFields::normaliseHeader('Société'));

        $mapping = BatchFields::guessMapping(['Full Name', 'Job Title', 'E-mail', 'Cell Phone', 'Company', '', 'Unrelated', 'Name']);
        $this->assertSame(0, $mapping['full_name']); // first match wins, the later "Name" is ignored
        $this->assertSame(1, $mapping['professional_title']);
        $this->assertSame(3, $mapping['mobile']);
        $this->assertSame(4, $mapping['company_name']);
        $this->assertSame(2, $mapping['email']);
        $this->assertNotContains(5, $mapping);
        $this->assertNotContains(6, $mapping);
    }

    public function test_csv_export_neutralises_formulas_but_keeps_phone_numbers(): void
    {
        $csv = app(CardExportService::class)->csv([
            ['Name', 'Phone', 'Note'],
            ['=HYPERLINK("http://evil")', '+44 20 7946 0000', '@SUM(A1)'],
            ['-1+2', '(555) 010-2000', "\tTab"],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil"")"', $csv);
        $this->assertStringContainsString('"+44 20 7946 0000"', $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
        $this->assertStringContainsString("'-1+2", $csv);
        $this->assertStringContainsString('"(555) 010-2000"', $csv);
    }

    public function test_public_codes_use_an_unambiguous_alphabet(): void
    {
        foreach (range(1, 50) as $_) {
            $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{12}$/', ProfileCodes::random(12));
        }
    }

    public function test_google_font_urls_only_include_known_families(): void
    {
        $url = Fonts::googleUrl(['Lato', 'Inter', 'Lato', 'Evil Font', "Inter'); x"]);
        $this->assertStringStartsWith('https://fonts.googleapis.com/css2?family=Inter:wght@', $url);
        $this->assertStringContainsString('&family=Lato:wght@', $url);
        $this->assertStringNotContainsString('Evil', $url);
        $this->assertSame(1, substr_count($url, 'family=Lato'));
        $this->assertNull(Fonts::googleUrl(['Nope']));
        $this->assertStringStartsWith("'ONeil', ", Fonts::stack("O'Neil")); // quotes cannot break out of CSS
    }
}
