<?php

namespace Tests\Unit\Scene;

use App\Services\CardRenderingService;
use App\Support\Scene\SceneValidator;
use App\Support\Themes\ThemeLibrary;
use Tests\TestCase;

class CardRenderingServiceTest extends TestCase
{
    private function render(array $side, array $options = []): string
    {
        return app(CardRenderingService::class)->render(app(SceneValidator::class)->validate($side), $options);
    }

    public function test_output_is_well_formed_svg_in_millimetres(): void
    {
        $svg = $this->render(['elements' => []], ['width_mm' => 85.6, 'height_mm' => 54, 'bleed_mm' => 3]);

        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml);
        $this->assertSame('91.6mm', (string) $xml['width']);
        $this->assertSame('-3 -3 91.6 60', (string) $xml['viewBox']);
    }

    public function test_merge_fields_are_filled_and_escaped(): void
    {
        $svg = $this->render(
            ['elements' => [['type' => 'text', 'text' => 'Hi {{full_name}}', 'width' => 50]]],
            ['data' => ['full_name' => '<script>alert("x")</script> & Co']],
        );

        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
        $this->assertStringContainsString('&amp; Co', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));
    }

    public function test_palette_tokens_resolve_and_hidden_elements_are_skipped(): void
    {
        $svg = $this->render(['elements' => [
            ['type' => 'shape', 'fill' => 'palette.primary', 'id' => 'visible'],
            ['type' => 'shape', 'fill' => '#00ff00', 'hidden' => true, 'id' => 'hidden'],
        ]], ['palette' => ['primary' => '#123456']]);

        $this->assertStringContainsString('fill="#123456"', $svg);
        $this->assertStringContainsString('data-id="visible"', $svg);
        $this->assertStringNotContainsString('data-id="hidden"', $svg);
    }

    public function test_qr_codes_are_vector_paths(): void
    {
        $svg = $this->render(['elements' => [['type' => 'qr', 'data' => 'https://example.com/card/x', 'width' => 20, 'height' => 20]]]);

        $this->assertMatchesRegularExpression('/<path[^>]+shape-rendering="crispEdges" d="M\d/', $svg);
    }

    public function test_every_library_theme_renders_valid_svg(): void
    {
        $themes = ThemeLibrary::all();
        $this->assertCount(28, $themes);
        $this->assertCount(28, array_unique(array_column($themes, 'slug')));

        foreach ($themes as $theme) {
            foreach (['front', 'back'] as $side) {
                $svg = $this->render($theme[$side], ['palette' => $theme['palette']]);
                $this->assertNotFalse(@simplexml_load_string($svg), "{$theme['name']} $side is not valid XML");
                $this->assertStringContainsString('<text', $svg, "{$theme['name']} $side has no text");
            }
        }
    }

    public function test_guides_and_corner_clipping(): void
    {
        $svg = $this->render(['elements' => []], ['bleed_mm' => 3, 'show_guides' => true, 'clip_corners' => true, 'corner_radius_mm' => 3]);

        $this->assertStringContainsString('data-guides="1"', $svg);
        $this->assertStringContainsString('<clipPath', $svg);
    }
}
