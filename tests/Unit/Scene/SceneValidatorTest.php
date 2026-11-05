<?php

namespace Tests\Unit\Scene;

use App\Support\Scene\SceneValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SceneValidatorTest extends TestCase
{
    private function validate(array $side): array
    {
        return app(SceneValidator::class)->validate($side);
    }

    public function test_it_normalises_elements_and_drops_unknown_keys(): void
    {
        $clean = $this->validate(['background' => ['type' => 'solid', 'color' => '#fff'], 'elements' => [
            ['type' => 'text', 'text' => 'Hi', 'x' => 5, 'y' => 5, 'width' => 30, 'evil' => '<script>', 'fontSize' => 9999],
        ]]);

        $el = $clean['elements'][0];
        $this->assertArrayNotHasKey('evil', $el);
        $this->assertSame(200.0, $el['fontSize']); // clamped
        $this->assertMatchesRegularExpression('/^el_[a-z0-9]{8}$/', $el['id']);
    }

    public function test_it_rejects_bad_colours_types_and_image_sources(): void
    {
        try {
            $this->validate(['elements' => [
                ['type' => 'text', 'color' => 'red;}</style><script>'],
                ['type' => 'hack'],
                ['type' => 'image', 'src' => 'javascript:alert(1)'],
                ['type' => 'image', 'src' => 'http://insecure.example.com/a.png'],
            ]]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $e) {
            $keys = array_keys($e->errors());
            $this->assertContains('scene.elements.0.color', $keys);
            $this->assertContains('scene.elements.1.type', $keys);
            $this->assertContains('scene.elements.2.src', $keys);
            $this->assertContains('scene.elements.3.src', $keys);
        }
    }

    public function test_it_accepts_palette_tokens_gradients_and_merge_images(): void
    {
        $clean = $this->validate([
            'background' => ['type' => 'gradient', 'angle' => 90, 'stops' => [['offset' => 1, 'color' => 'palette.secondary'], ['offset' => 0, 'color' => 'palette.primary']]],
            'elements' => [
                ['type' => 'logo'],
                ['type' => 'image', 'src' => '/storage/uploads/a.png'],
                ['type' => 'shape', 'fill' => ['type' => 'gradient', 'stops' => [['offset' => 0, 'color' => '#000'], ['offset' => 1, 'color' => 'rgba(0,0,0,0.5)']]]],
            ],
        ]);

        $this->assertSame(0.0, $clean['background']['stops'][0]['offset']); // sorted
        $this->assertSame('{{logo}}', $clean['elements'][0]['src']);
        $this->assertSame('gradient', $clean['elements'][2]['fill']['type']);
    }

    public function test_duplicate_ids_are_reassigned(): void
    {
        $clean = $this->validate(['elements' => [['type' => 'line', 'id' => 'a'], ['type' => 'line', 'id' => 'a']]]);
        $this->assertNotSame($clean['elements'][0]['id'], $clean['elements'][1]['id']);
    }
}
