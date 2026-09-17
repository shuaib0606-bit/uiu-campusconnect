<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Shrinks an uploaded picture in place. A gallery accepts ten images of up to
 * 10 MB each, and serving those at full size is the heaviest thing the site
 * does to a slow connection.
 */
class ImageResizer
{
    /** Longest edge kept, in pixels. Wide enough for a full-width cover. */
    public const MAX_EDGE = 1600;

    /** JPEG quality. Above about 85 the file grows faster than the quality. */
    public const QUALITY = 82;

    /**
     * Resizes the stored file if it is larger than MAX_EDGE.
     *
     * Returns true when the file was rewritten. Any problem leaves the
     * original untouched, because a slightly heavy picture beats a lost one.
     */
    public static function shrink(string $path, string $disk = 'public'): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $full = Storage::disk($disk)->path($path);

        if (! is_file($full)) {
            return false;
        }

        $info = @getimagesize($full);

        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;
        $type = $info[2];

        if (max($width, $height) <= self::MAX_EDGE) {
            return false;
        }

        $source = self::read($full, $type);

        if ($source === null) {
            return false;
        }

        $scale = self::MAX_EDGE / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        // PNG and GIF can carry transparency, which a plain copy would blacken.
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $written = self::write($target, $full, $type);

        imagedestroy($source);
        imagedestroy($target);

        return $written;
    }

    /** @return \GdImage|null */
    private static function read(string $file, int $type)
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_GIF => @imagecreatefromgif($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => false,
        };

        return $image === false ? null : $image;
    }

    private static function write($image, string $file, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagejpeg($image, $file, self::QUALITY),
            IMAGETYPE_PNG => @imagepng($image, $file, 6),
            IMAGETYPE_GIF => @imagegif($image, $file),
            IMAGETYPE_WEBP => @imagewebp($image, $file, self::QUALITY),
            default => false,
        };
    }
}
