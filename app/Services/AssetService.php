<?php

namespace App\Services;

use App\Models\UploadedAsset;
use App\Models\User;
use App\Support\Fonts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Stores user uploads used in card designs. Raster images are decoded and
 * re-encoded (which strips metadata and anything that is not pixel data);
 * SVG uploads are not accepted. Fonts are checked by their file signature.
 */
class AssetService
{
    public const IMAGE_TYPES = ['image', 'logo', 'photo', 'background'];

    public const MAX_IMAGE_EDGE = 4000; // px, larger images are scaled down

    public const MAX_IMAGE_KB = 10240;

    public const MAX_FONT_KB = 5120;

    private const FONT_SIGNATURES = ["\x00\x01\x00\x00" => 'ttf', 'true' => 'ttf', 'OTTO' => 'otf', 'wOFF' => 'woff', 'wOF2' => 'woff2'];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function storeImage(User $user, UploadedFile $file, string $type = 'image'): UploadedAsset
    {
        $manager = new ImageManager(new Driver);
        try {
            $image = $manager->read($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The file could not be read as an image.']);
        }

        if ($image->width() > self::MAX_IMAGE_EDGE || $image->height() > self::MAX_IMAGE_EDGE) {
            $image->scaleDown(self::MAX_IMAGE_EDGE, self::MAX_IMAGE_EDGE);
        }

        $mime = $file->getMimeType();
        [$encoded, $ext, $mime] = match ($mime) {
            'image/png' => [$image->toPng(), 'png', 'image/png'],
            'image/webp' => [$image->toWebp(90), 'webp', 'image/webp'],
            default => [$image->toJpeg(90), 'jpg', 'image/jpeg'],
        };

        $ulid = (string) Str::ulid();
        $path = 'assets/'.$user->ulid.'/'.Str::lower($ulid).'.'.$ext;
        Storage::disk('public')->put($path, (string) $encoded);

        $asset = $user->uploadedAssets()->create([
            'type' => in_array($type, self::IMAGE_TYPES, true) ? $type : 'image',
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => $mime,
            'size' => strlen((string) $encoded),
            'width' => $image->width(),
            'height' => $image->height(),
        ]);
        $this->logger->log('asset.uploaded', $asset, 'Uploaded '.$asset->type.' "'.$asset->original_name.'"');

        return $asset;
    }

    public function storeFont(User $user, UploadedFile $file, string $family): UploadedAsset
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }
        $ext = self::FONT_SIGNATURES[$magic] ?? null;
        if (! $ext) {
            throw ValidationException::withMessages(['file' => 'The file is not a TTF, OTF, WOFF or WOFF2 font.']);
        }

        $path = 'fonts/'.$user->ulid.'/'.Str::lower((string) Str::ulid()).'.'.$ext;
        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        $asset = $user->uploadedAssets()->create([
            'type' => 'font',
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => 'font/'.$ext,
            'size' => $file->getSize(),
            'meta' => ['family' => $family, 'format' => $ext],
        ]);
        $this->logger->log('asset.uploaded', $asset, 'Uploaded font "'.$family.'"');

        return $asset;
    }

    public function delete(UploadedAsset $asset): void
    {
        Storage::disk($asset->disk)->delete($asset->path);
        $this->logger->log('asset.deleted', $asset, 'Deleted '.$asset->type.' "'.$asset->original_name.'"');
        $asset->delete();
    }

    /** @font-face CSS for a user's uploaded fonts. */
    public function fontFaceCss(iterable $fonts): string
    {
        $css = '';
        foreach ($fonts as $font) {
            $format = ['ttf' => 'truetype', 'otf' => 'opentype', 'woff' => 'woff', 'woff2' => 'woff2'][$font->meta['format'] ?? 'ttf'] ?? 'truetype';
            $family = str_replace(["'", '\\', '<', '>'], '', (string) ($font->meta['family'] ?? ''));
            $css .= "@font-face{font-family:'{$family}';src:url('".e(parse_url($font->url(), PHP_URL_PATH) ?: $font->url())."') format('{$format}');font-display:swap;}";
        }

        return $css;
    }

    /** @font-face CSS for uploaded fonts (by family) that belong to any of the given users. */
    public function fontFaceCssFor(array $families, array $userIds): string
    {
        $families = array_values(array_filter(array_unique($families), fn ($f) => ! Fonts::isGoogle($f)));
        if (! $families || ! $userIds) {
            return '';
        }

        $fonts = UploadedAsset::where('type', 'font')->whereIn('user_id', array_unique(array_filter($userIds)))->latest()->get()
            ->filter(fn ($f) => in_array($f->meta['family'] ?? null, $families, true))
            ->unique(fn ($f) => $f->meta['family']);

        return $this->fontFaceCss($fonts);
    }

    public function toArray(UploadedAsset $asset): array
    {
        return [
            'id' => $asset->ulid,
            'type' => $asset->type,
            'name' => $asset->original_name,
            'url' => $asset->url(),
            // Same-origin path: works behind any host name (fonts require same origin or CORS).
            'src' => parse_url($asset->url(), PHP_URL_PATH) ?: $asset->url(),
            'width' => $asset->width,
            'height' => $asset->height,
            'family' => $asset->meta['family'] ?? null,
        ];
    }
}
