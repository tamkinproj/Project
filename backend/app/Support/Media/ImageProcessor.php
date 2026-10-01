<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Decodes an uploaded image and re-encodes it as WebP. Re-encoding strips EXIF/GPS
 * metadata (location privacy) and discards any non-image payload hidden in the file.
 */
class ImageProcessor
{
    /** @return array{disk: string, path: string, mime_type: string, width: int, height: int, size_bytes: int} */
    public function store(UploadedFile $file, string $directory, ?int $squareSize = null): array
    {
        $contents = file_get_contents($file->getRealPath());
        $info = $contents === false ? false : @getimagesizefromstring($contents);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['image' => 'The file is not a supported image.']);
        }

        [$width, $height] = $info;
        $maxDimension = config('ecosystem.media.max_dimension');

        if ($width < 1 || $height < 1 || $width * $height > 40_000_000) {
            throw ValidationException::withMessages(['image' => 'The image dimensions are not supported.']);
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'The image could not be read.']);
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = $this->applyExifOrientation($source, $contents);
            [$width, $height] = [imagesx($source), imagesy($source)];
        }

        if ($squareSize !== null) {
            $side = min($width, $height);
            $target = imagecreatetruecolor($squareSize, $squareSize);
            $this->prepareCanvas($target);
            imagecopyresampled($target, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $squareSize, $squareSize, $side, $side);
        } else {
            $scale = min(1, $maxDimension / max($width, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($newWidth, $newHeight);
            $this->prepareCanvas($target);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        }

        imagedestroy($source);

        ob_start();
        imagewebp($target, null, 82);
        $encoded = (string) ob_get_clean();
        $finalWidth = imagesx($target);
        $finalHeight = imagesy($target);
        imagedestroy($target);

        $disk = config('ecosystem.media.disk');
        $path = trim($directory, '/').'/'.Str::ulid()->toBase32().'.webp';
        Storage::disk($disk)->put($path, $encoded);

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'image/webp',
            'width' => $finalWidth,
            'height' => $finalHeight,
            'size_bytes' => strlen($encoded),
        ];
    }

    private function prepareCanvas(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    private function applyExifOrientation(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $rotation = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);

        return $rotated === false ? $image : $rotated;
    }
}
