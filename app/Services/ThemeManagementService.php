<?php

namespace App\Services;

use App\Models\CardTheme;
use App\Models\User;
use App\Support\Scene\Scene;
use App\Support\Scene\SceneValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Theme lifecycle: creation, customisation, duplication, reset, publishing,
 * favourites and previews. Themes are always stored as editable scene data.
 */
class ThemeManagementService
{
    public const STATUS_PRIVATE = 'private';

    public function __construct(
        private readonly SceneValidator $validator,
        private readonly CardRenderingService $renderer,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(array $data, User $user): CardTheme
    {
        $theme = CardTheme::create($this->attributes($data) + [
            'created_by' => $user->id,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => CardTheme::STATUS_DRAFT,
            'front' => $this->validator->validate($data['front'] ?? Scene::emptySide(), 'front'),
            'back' => $this->validator->validate($data['back'] ?? Scene::emptySide('#111827'), 'back'),
        ]);

        $this->logger->log('theme.created', $theme, "Created theme {$theme->name}");

        return $theme;
    }

    public function update(CardTheme $theme, array $data): CardTheme
    {
        $attributes = $this->attributes($data);
        if (array_key_exists('front', $data)) {
            $attributes['front'] = $this->validator->validate($data['front'], 'front');
        }
        if (array_key_exists('back', $data)) {
            $attributes['back'] = $this->validator->validate($data['back'], 'back');
        }
        if (isset($data['name']) && $data['name'] !== $theme->name) {
            $attributes['slug'] = $this->uniqueSlug($data['name'], $theme->id);
        }

        $theme->update($attributes);
        $this->logger->log('theme.updated', $theme, "Updated theme {$theme->name}");

        return $theme->refresh();
    }

    /**
     * Copy a theme. Staff copies start as drafts (for publishing later);
     * everyone else gets a private copy only they can see and edit.
     */
    public function duplicate(CardTheme $theme, User $user, ?string $name = null): CardTheme
    {
        $name ??= $theme->name.' (copy)';

        $copy = $theme->replicate(['ulid', 'slug', 'usage_count', 'published_at', 'thumbnail_path']);
        $copy->fill([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'parent_id' => $theme->id,
            'created_by' => $user->id,
            'is_system' => false,
            'status' => $user->can('themes.manage') ? CardTheme::STATUS_DRAFT : self::STATUS_PRIVATE,
            'sort_order' => 1000,
        ])->save();

        $this->logger->log('theme.duplicated', $copy, "Duplicated {$theme->name} as {$copy->name}", ['source' => $theme->ulid]);

        return $copy;
    }

    /** Save a customised version of a theme as a new theme ("Save as new theme"). */
    public function saveAsNew(CardTheme $theme, User $user, array $customization): CardTheme
    {
        return DB::transaction(function () use ($theme, $user, $customization) {
            $copy = $this->duplicate($theme, $user, $customization['name'] ?? $theme->name.' (custom)');
            $copy->update($this->customizedAttributes($theme, $customization));
            $theme->increment('usage_count');

            return $copy->refresh();
        });
    }

    /** Apply customisation to an existing (owned) theme in place. */
    public function applyCustomization(CardTheme $theme, array $customization): CardTheme
    {
        $attributes = $this->customizedAttributes($theme, $customization);
        if (! empty($customization['name']) && $customization['name'] !== $theme->name) {
            $attributes['name'] = $customization['name'];
            $attributes['slug'] = $this->uniqueSlug($customization['name'], $theme->id);
        }
        $theme->update($attributes);
        $this->logger->log('theme.customized', $theme, "Customised theme {$theme->name}");

        return $theme->refresh();
    }

    /** Restore palette, fonts, layout and both sides from the theme it was copied from. */
    public function resetToOriginal(CardTheme $theme): CardTheme
    {
        $original = $theme->parent()->withTrashed()->first();
        abort_unless($original, 422, 'This theme has no original to reset to.');

        $theme->update(Arr::only($original->toArray(), ['palette', 'fonts', 'front', 'back', 'layout', 'width_mm', 'height_mm', 'orientation']));
        $this->logger->log('theme.reset', $theme, "Reset {$theme->name} to {$original->name}");

        return $theme->refresh();
    }

    public function publish(CardTheme $theme): CardTheme
    {
        $theme->update(['status' => CardTheme::STATUS_PUBLISHED, 'published_at' => $theme->published_at ?? now()]);
        $this->logger->log('theme.published', $theme, "Published theme {$theme->name}");

        return $theme;
    }

    public function unpublish(CardTheme $theme): CardTheme
    {
        $theme->update(['status' => CardTheme::STATUS_DRAFT]);
        $this->logger->log('theme.unpublished', $theme, "Unpublished theme {$theme->name}");

        return $theme;
    }

    public function delete(CardTheme $theme): void
    {
        $theme->delete();
        $this->logger->log('theme.deleted', $theme, "Deleted theme {$theme->name}");
    }

    /** @return bool whether the theme is now a favourite */
    public function toggleFavorite(CardTheme $theme, User $user): bool
    {
        $result = $user->favoriteThemes()->toggle($theme->id);

        return count($result['attached']) > 0;
    }

    /**
     * Render front and back previews. $overrides may contain palette, fonts,
     * corner_radius_mm, front and back (unsaved changes from the customiser).
     *
     * @return array{front: string, back: string, fonts: array}
     */
    public function preview(CardTheme $theme, array $overrides = [], array $options = []): array
    {
        $render = function () use ($theme, $overrides, $options) {
            $config = $overrides ? array_merge($theme->only(['palette', 'fonts', 'front', 'back', 'layout', 'width_mm', 'height_mm']), $this->customizedAttributes($theme, $overrides)) : $theme->only(['palette', 'fonts', 'front', 'back', 'layout', 'width_mm', 'height_mm']);
            $base = [
                'width_mm' => (float) $config['width_mm'],
                'height_mm' => (float) $config['height_mm'],
                'palette' => $config['palette'] ?? [],
                'corner_radius_mm' => (float) ($config['layout']['corner_radius_mm'] ?? 0),
                'clip_corners' => true,
            ] + $options;

            return [
                'front' => $this->renderer->render($config['front'] ?? Scene::emptySide(), $base + ['title' => $theme->name.' front']),
                'back' => $this->renderer->render($config['back'] ?? Scene::emptySide(), $base + ['title' => $theme->name.' back']),
                'fonts' => $this->renderer->fontsUsed($config['front'] ?? [], $config['back'] ?? []),
            ];
        };

        if ($overrides || $options) {
            return $render();
        }

        return Cache::remember("theme-preview:{$theme->ulid}:".$theme->updated_at?->timestamp, now()->addDay(), $render);
    }

    /** Attributes resulting from applying a customisation to a theme's current config. */
    public function customizedAttributes(CardTheme $theme, array $c): array
    {
        $palette = array_merge($theme->palette ?? [], Arr::only($c['palette'] ?? [], Scene::PALETTE_KEYS));
        $oldFonts = $theme->fonts ?? ['heading' => 'Inter', 'body' => 'Inter'];
        $fonts = array_merge($oldFonts, Arr::only($c['fonts'] ?? [], ['heading', 'body']));

        $front = isset($c['front']) ? $this->validator->validate($c['front'], 'front') : $this->swapFonts($theme->front ?? Scene::emptySide(), $oldFonts, $fonts);
        $back = isset($c['back']) ? $this->validator->validate($c['back'], 'back') : $this->swapFonts($theme->back ?? Scene::emptySide(), $oldFonts, $fonts);

        $layout = $theme->layout ?? [];
        if (isset($c['corner_radius_mm'])) {
            $layout['corner_radius_mm'] = max(0, min(10, (float) $c['corner_radius_mm']));
        }

        return compact('palette', 'fonts', 'front', 'back', 'layout');
    }

    /** Replace the theme's heading/body font families inside scene text elements. */
    private function swapFonts(array $side, array $old, array $new): array
    {
        $map = [];
        if (($old['heading'] ?? null) !== ($new['heading'] ?? null)) {
            $map[$old['heading']] = $new['heading'];
        }
        if (($old['body'] ?? null) !== ($new['body'] ?? null) && ! isset($map[$old['body']])) {
            $map[$old['body']] = $new['body'];
        }
        if (! $map) {
            return $side;
        }

        $headingSameAsBody = ($old['heading'] ?? null) === ($old['body'] ?? null);
        $side['elements'] = array_map(function ($el) use ($map, $new, $headingSameAsBody) {
            if (in_array($el['type'] ?? null, ['text', 'heading'], true) && isset($map[$el['fontFamily'] ?? ''])) {
                // When heading and body shared one font, headings follow the new heading font and text the new body font.
                $el['fontFamily'] = $headingSameAsBody
                    ? ($el['type'] === 'heading' ? $new['heading'] : $new['body'])
                    : $map[$el['fontFamily']];
            }

            return $el;
        }, $side['elements'] ?? []);

        return $side;
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'category', 'description', 'is_premium', 'sort_order', 'width_mm', 'height_mm', 'orientation', 'palette', 'fonts']);
        if (isset($data['layout'])) {
            $attributes['layout'] = Arr::only($data['layout'], ['archetype', 'bleed_mm', 'safe_mm', 'corner_radius_mm']);
        }

        return $attributes;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $i = 2;
        while (CardTheme::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
