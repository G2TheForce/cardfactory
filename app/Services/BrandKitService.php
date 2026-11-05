<?php

namespace App\Services;

use App\Models\BrandKit;
use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\User;
use App\Support\CustomerFields;
use App\Support\Scene\Scene;
use App\Support\Scene\SceneValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brand kits: a company's logo, colours, typography, background, company
 * details and social links in one place. Applying a kit to a design swaps
 * in its palette and fonts (and optionally its background), and its logo
 * and company details fill the card's merge fields when the customer has
 * none. A brand can hold any number of card templates (designs).
 */
class BrandKitService
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly ThemeManagementService $themes,
        private readonly CardDesignService $designs,
        private readonly SceneValidator $validator,
        private readonly ActivityLogger $logger,
    ) {}

    /** @param  array{logo?: ?UploadedFile, background_image?: ?UploadedFile}  $files */
    public function save(?BrandKit $kit, array $data, User $actor, array $files = []): BrandKit
    {
        $isNew = $kit === null;
        $stored = [];

        try {
            return DB::transaction(function () use ($kit, $data, $actor, $files, $isNew, &$stored) {
                $kit ??= new BrandKit(['user_id' => $actor->id]);
                $kit->ulid ??= (string) Str::ulid();
                $old = [];

                $kit->fill([
                    'name' => trim($data['name']),
                    'primary_color' => strtoupper($data['primary_color']),
                    'secondary_color' => strtoupper($data['secondary_color']),
                    'accent_color' => strtoupper($data['accent_color']),
                    'typography' => ['heading' => $data['heading_font'], 'body' => $data['body_font']],
                    'company_details' => array_filter(Arr::only($data['company'] ?? [], ['name', 'tagline', 'website', 'phone', 'email', 'address']), fn ($v) => filled($v)),
                    'social_links' => array_filter(Arr::only($data['social_links'] ?? [], array_keys(CustomerFields::SOCIAL)), fn ($v) => filled($v)),
                ]);

                if (($logo = $files['logo'] ?? null) instanceof UploadedFile) {
                    $old[] = $kit->logo_path;
                    $kit->logo_path = $stored[] = $this->assets->storeImageFile($logo, 'brand-kits/'.$kit->ulid, 2000, 'logo')['path'];
                } elseif (! empty($data['remove_logo'])) {
                    $old[] = $kit->logo_path;
                    $kit->logo_path = null;
                }

                $background = $kit->background ?? [];
                $image = $background['image_path'] ?? null;
                if (($bg = $files['background_image'] ?? null) instanceof UploadedFile) {
                    $old[] = $image;
                    $image = $stored[] = $this->assets->storeImageFile($bg, 'brand-kits/'.$kit->ulid, 3000, 'background_image')['path'];
                } elseif (! empty($data['remove_background_image'])) {
                    $old[] = $image;
                    $image = null;
                }
                $type = $data['background_type'] ?? 'solid';
                if ($type === 'image' && ! $image) {
                    $type = 'solid';
                }
                $kit->background = array_filter([
                    'type' => $type,
                    'color' => strtoupper($data['background_color'] ?? '#FFFFFF'),
                    'color2' => strtoupper($data['background_color2'] ?? $data['primary_color']),
                    'angle' => (int) ($data['background_angle'] ?? 135),
                    'image_path' => $image,
                ], fn ($v) => $v !== null);

                $kit->save();
                $paths = array_filter($old);
                DB::afterCommit(fn () => Storage::disk('public')->delete($paths));
                $this->logger->log($isNew ? 'brand_kit.created' : 'brand_kit.updated', $kit, ($isNew ? 'Created' : 'Updated').' brand kit "'.$kit->name.'"', [], $actor->id);

                return $kit;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }
    }

    public function delete(BrandKit $kit, User $actor): void
    {
        DB::transaction(function () use ($kit, $actor) {
            CardDesign::where('brand_kit_id', $kit->id)->update(['brand_kit_id' => null]);
            $this->logger->log('brand_kit.deleted', $kit, 'Deleted brand kit "'.$kit->name.'"', [], $actor->id);
            $kit->delete();
        });
    }

    /** Scene background for the kit (solid colour, gradient or image). */
    public function sceneBackground(BrandKit $kit): array
    {
        $bg = $kit->background ?? [];

        return match ($bg['type'] ?? 'solid') {
            'gradient' => ['type' => 'gradient', 'angle' => $bg['angle'] ?? 135, 'stops' => [
                ['offset' => 0, 'color' => $bg['color'] ?? '#FFFFFF'], ['offset' => 1, 'color' => $bg['color2'] ?? $kit->primary_color],
            ]],
            'image' => ['type' => 'image', 'src' => Storage::disk('public')->url($bg['image_path']), 'fit' => 'cover', 'color' => $bg['color'] ?? '#FFFFFF'],
            default => ['type' => 'solid', 'color' => $bg['color'] ?? '#FFFFFF'],
        };
    }

    /**
     * Apply the kit's colours and fonts to a design, and its background to
     * the chosen sides. Bumps the design version so open editors reload.
     *
     * @param  array<int, string>  $backgroundSides  'front' and/or 'back'
     */
    public function apply(BrandKit $kit, CardDesign $design, User $actor, array $backgroundSides = []): CardDesign
    {
        return DB::transaction(function () use ($kit, $design, $actor, $backgroundSides) {
            $settings = $design->settings ?? [];
            $oldFonts = $settings['fonts'] ?? ['heading' => 'Inter', 'body' => 'Inter'];
            $newFonts = array_merge($oldFonts, Arr::only($kit->typography ?? [], ['heading', 'body']));

            $front = $this->themes->swapFonts($design->front ?? Scene::emptySide(), $oldFonts, $newFonts);
            $back = $this->themes->swapFonts($design->back ?? Scene::emptySide(), $oldFonts, $newFonts);
            foreach (array_intersect($backgroundSides, ['front', 'back']) as $side) {
                ${$side}['background'] = $this->sceneBackground($kit);
            }

            $settings['palette'] = array_merge($settings['palette'] ?? [], [
                'primary' => $kit->primary_color, 'secondary' => $kit->secondary_color, 'accent' => $kit->accent_color,
            ]);
            $settings['fonts'] = $newFonts;

            $design->forceFill([
                'brand_kit_id' => $kit->id,
                'front' => $this->validator->validate($front, 'front'),
                'back' => $this->validator->validate($back, 'back'),
                'settings' => $settings,
                'version' => (int) $design->version + 1,
            ])->save();
            $this->designs->syncElements($design);
            $this->logger->log('brand_kit.applied', $design, 'Applied brand kit "'.$kit->name.'" to "'.$design->name.'"', [], $actor->id);

            return $design;
        });
    }

    /** New design (template) for this brand, from a theme, with the kit applied. */
    public function createDesign(BrandKit $kit, CardTheme $theme, User $actor, ?string $name = null): CardDesign
    {
        return DB::transaction(function () use ($kit, $theme, $actor, $name) {
            $design = $this->designs->create($actor, ['name' => $name ?: $kit->name.' · '.$theme->name, 'theme' => $theme]);

            return $this->apply($kit, $design, $actor, ['back']);
        });
    }
}
