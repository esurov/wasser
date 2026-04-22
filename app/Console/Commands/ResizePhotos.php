<?php

namespace App\Console\Commands;

use App\Models\FountainPhoto;
use GdImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('photos:resize
    {--max-edge=1600 : Maximum long edge in pixels}
    {--quality=85 : JPEG/WEBP quality (1-100)}
    {--min-kb=500 : Skip files at or below N KB when dimensions already fit}
    {--dry-run : Report what would change without writing files}')]
#[Description('Resize existing fountain photos in place to reduce disk usage')]
class ResizePhotos extends Command
{
    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD extension is required.');

            return self::FAILURE;
        }

        $maxEdge = (int) $this->option('max-edge');
        $quality = (int) $this->option('quality');
        $minBytes = (int) $this->option('min-kb') * 1024;
        $dryRun = (bool) $this->option('dry-run');

        $disk = Storage::disk('public');
        $total = FountainPhoto::query()->count();

        $this->info("Scanning {$total} photos — max-edge={$maxEdge}px, quality={$quality}".($dryRun ? ' (dry run)' : ''));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $skipped = 0;
        $resized = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        FountainPhoto::query()->orderBy('id')->lazy()->each(function (FountainPhoto $photo) use (
            $disk, $maxEdge, $quality, $minBytes, $dryRun, $bar,
            &$skipped, &$resized, &$failed, &$bytesBefore, &$bytesAfter,
        ) {
            if (! $disk->exists($photo->path)) {
                $this->newLine();
                $this->warn("Missing file: {$photo->path}");
                $failed++;
                $bar->advance();

                return;
            }

            $absolute = $disk->path($photo->path);
            $sizeBefore = filesize($absolute) ?: 0;

            try {
                $newSize = $this->processFile($absolute, $maxEdge, $quality, $minBytes, $dryRun);
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("Failed {$photo->path}: {$e->getMessage()}");
                $failed++;
                $bar->advance();

                return;
            }

            if ($newSize === null) {
                $skipped++;
            } else {
                $resized++;
                if (! $dryRun) {
                    $bytesBefore += $sizeBefore;
                    $bytesAfter += $newSize;
                }
            }
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Resized: {$resized}".($dryRun ? ' (would resize)' : ''));
        $this->info("Skipped: {$skipped}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }
        if ($resized > 0 && ! $dryRun) {
            $savedKb = (int) round(($bytesBefore - $bytesAfter) / 1024);
            $beforeKb = (int) round($bytesBefore / 1024);
            $afterKb = (int) round($bytesAfter / 1024);
            $this->info("Bytes saved: {$savedKb} KB ({$beforeKb} KB → {$afterKb} KB)");
        }

        return self::SUCCESS;
    }

    /**
     * Resize a single file in place.
     *
     * @return int|null New size in bytes, or null if nothing to do.
     */
    private function processFile(string $path, int $maxEdge, int $quality, int $minBytes, bool $dryRun): ?int
    {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('not a recognized image');
        }

        [$width, $height, $type] = $info;
        $fileSize = filesize($path) ?: 0;

        if (max($width, $height) <= $maxEdge && $fileSize <= $minBytes) {
            return null;
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => throw new RuntimeException('unsupported type: '.image_type_to_mime_type($type)),
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('decode failed');
        }

        try {
            if ($type === IMAGETYPE_JPEG) {
                $image = $this->applyExifRotation($image, $path);
                $width = imagesx($image);
                $height = imagesy($image);
            }

            $longEdge = max($width, $height);
            if ($longEdge > $maxEdge) {
                $scale = $maxEdge / $longEdge;
                $newW = (int) round($width * $scale);
                $newH = (int) round($height * $scale);
                $image = $this->resample($image, $newW, $newH, $type);
            }

            if ($dryRun) {
                return 0;
            }

            $tmp = $path.'.tmp';
            $ok = match ($type) {
                IMAGETYPE_JPEG => imagejpeg($image, $tmp, $quality),
                IMAGETYPE_PNG => imagepng($image, $tmp, 6),
                IMAGETYPE_WEBP => imagewebp($image, $tmp, $quality),
            };

            if (! $ok) {
                @unlink($tmp);
                throw new RuntimeException('encode failed');
            }

            rename($tmp, $path);

            return filesize($path) ?: 0;
        } finally {
            imagedestroy($image);
        }
    }

    private function resample(GdImage $src, int $width, int $height, int $type): GdImage
    {
        $dst = imagecreatetruecolor($width, $height);
        if ($dst === false) {
            throw new RuntimeException('canvas allocation failed');
        }

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
            }
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
        imagedestroy($src);

        return $dst;
    }

    private function applyExifRotation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        if (! is_array($exif) || empty($exif['Orientation'])) {
            return $image;
        }

        $angle = match ((int) $exif['Orientation']) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
