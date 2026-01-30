<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\JpegEncoder;

class ImageOptimizationService
{
    /**
     * Default image sizes for different use cases.
     */
    protected array $sizes = [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 300, 'height' => 300],
        'medium' => ['width' => 600, 'height' => 600],
        'large' => ['width' => 1200, 'height' => 1200],
        'profile' => ['width' => 400, 'height' => 400],
        'gallery' => ['width' => 800, 'height' => 800],
    ];

    /**
     * Default quality settings.
     */
    protected int $jpegQuality = 85;
    protected int $webpQuality = 80;

    /**
     * Process and optimize an uploaded image.
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param array $variants Sizes to generate (e.g., ['thumbnail', 'medium'])
     * @param bool $generateWebp Whether to generate WebP versions
     * @return array Paths to all generated images
     */
    public function processUpload(
        UploadedFile $file,
        string $directory = 'photos',
        array $variants = ['thumbnail', 'medium', 'large'],
        bool $generateWebp = true
    ): array {
        $filename = $this->generateFilename($file);
        $paths = [];

        // Process original (optimized)
        $originalPath = $this->processOriginal($file, $directory, $filename);
        $paths['original'] = $originalPath;

        // Generate variants
        foreach ($variants as $variant) {
            if (isset($this->sizes[$variant])) {
                $variantPath = $this->generateVariant(
                    $file,
                    $directory,
                    $filename,
                    $variant,
                    $this->sizes[$variant]
                );
                $paths[$variant] = $variantPath;

                // Generate WebP version
                if ($generateWebp) {
                    $webpPath = $this->generateWebpVariant(
                        $file,
                        $directory,
                        $filename,
                        $variant,
                        $this->sizes[$variant]
                    );
                    $paths["{$variant}_webp"] = $webpPath;
                }
            }
        }

        return $paths;
    }

    /**
     * Process and optimize the original image.
     */
    protected function processOriginal(
        UploadedFile $file,
        string $directory,
        string $filename
    ): string {
        $image = Image::read($file->getRealPath());

        // Auto-orient based on EXIF data
        $image->orient();

        // Resize if too large (max 2000px on longest side)
        $image->scaleDown(2000, 2000);

        // Encode as JPEG with optimization
        $encoded = $image->encode(new JpegEncoder($this->jpegQuality));

        $path = "{$directory}/original/{$filename}.jpg";
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Generate a resized variant of the image.
     */
    protected function generateVariant(
        UploadedFile $file,
        string $directory,
        string $filename,
        string $variant,
        array $size
    ): string {
        $image = Image::read($file->getRealPath());

        // Auto-orient
        $image->orient();

        // Cover resize (fill the dimensions, crop excess)
        $image->cover($size['width'], $size['height']);

        // Encode as JPEG
        $encoded = $image->encode(new JpegEncoder($this->jpegQuality));

        $path = "{$directory}/{$variant}/{$filename}.jpg";
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Generate a WebP variant of the image.
     */
    protected function generateWebpVariant(
        UploadedFile $file,
        string $directory,
        string $filename,
        string $variant,
        array $size
    ): string {
        $image = Image::read($file->getRealPath());

        // Auto-orient
        $image->orient();

        // Cover resize
        $image->cover($size['width'], $size['height']);

        // Encode as WebP
        $encoded = $image->encode(new WebpEncoder($this->webpQuality));

        $path = "{$directory}/{$variant}/{$filename}.webp";
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Generate a unique filename.
     */
    protected function generateFilename(UploadedFile $file): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Process a profile photo with specific optimizations.
     */
    public function processProfilePhoto(UploadedFile $file, int $userId): array
    {
        $directory = "users/{$userId}/photos";

        return $this->processUpload(
            $file,
            $directory,
            ['thumbnail', 'profile', 'medium'],
            true
        );
    }

    /**
     * Delete all variants of an image.
     */
    public function deleteImage(string $basePath): bool
    {
        $directory = dirname($basePath);
        $filename = pathinfo($basePath, PATHINFO_FILENAME);

        $deleted = true;

        // Delete all variants
        foreach (array_keys($this->sizes) as $variant) {
            $jpgPath = "{$directory}/{$variant}/{$filename}.jpg";
            $webpPath = "{$directory}/{$variant}/{$filename}.webp";

            if (Storage::disk('public')->exists($jpgPath)) {
                $deleted = $deleted && Storage::disk('public')->delete($jpgPath);
            }
            if (Storage::disk('public')->exists($webpPath)) {
                $deleted = $deleted && Storage::disk('public')->delete($webpPath);
            }
        }

        // Delete original
        $originalPath = "{$directory}/original/{$filename}.jpg";
        if (Storage::disk('public')->exists($originalPath)) {
            $deleted = $deleted && Storage::disk('public')->delete($originalPath);
        }

        return $deleted;
    }

    /**
     * Get the URL for an optimized image variant.
     */
    public function getImageUrl(string $basePath, string $variant = 'medium', bool $preferWebp = true): string
    {
        $directory = dirname($basePath);
        $filename = pathinfo($basePath, PATHINFO_FILENAME);

        if ($preferWebp) {
            $webpPath = "{$directory}/{$variant}/{$filename}.webp";
            if (Storage::disk('public')->exists($webpPath)) {
                return Storage::disk('public')->url($webpPath);
            }
        }

        $jpgPath = "{$directory}/{$variant}/{$filename}.jpg";
        if (Storage::disk('public')->exists($jpgPath)) {
            return Storage::disk('public')->url($jpgPath);
        }

        // Fallback to original
        return Storage::disk('public')->url($basePath);
    }

    /**
     * Set custom quality settings.
     */
    public function setQuality(int $jpeg = 85, int $webp = 80): self
    {
        $this->jpegQuality = $jpeg;
        $this->webpQuality = $webp;
        return $this;
    }

    /**
     * Add or override size configuration.
     */
    public function addSize(string $name, int $width, int $height): self
    {
        $this->sizes[$name] = ['width' => $width, 'height' => $height];
        return $this;
    }
}
