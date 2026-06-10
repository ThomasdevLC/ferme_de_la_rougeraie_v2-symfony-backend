<?php

namespace App\Service\Image;

final class ProductImageProcessor
{
    public function __construct(
        private readonly string $uploadDir,
        private readonly int $maxWidth,
        private readonly int $maxHeight,
        private readonly int $maxSourceWidth,
        private readonly int $maxSourceHeight,
        private readonly int $maxSourcePixels,
        private readonly int $jpegQuality,
        private readonly int $pngCompression,
        private readonly int $webpQuality,
    ) {
    }

    public function optimize(string $filename): void
    {
        $path = $this->resolvePath($filename);
        if (!is_file($path)) {
            return;
        }

        $imageInfo = @getimagesize($path);
        if (false === $imageInfo) {
            return;
        }

        $mimeType = $imageInfo['mime'] ?? null;
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }

        $this->guardSourceDimensions($filename, $imageInfo[0], $imageInfo[1]);

        $sourceImage = $this->createImageResource($path, $mimeType);
        if (null === $sourceImage) {
            return;
        }

        $workingImage = $sourceImage;

        try {
            $workingImage = $this->normalizeOrientation($sourceImage, $path, $mimeType);
            $workingWidth = imagesx($workingImage);
            $workingHeight = imagesy($workingImage);
            [$targetWidth, $targetHeight] = $this->computeTargetSize($workingWidth, $workingHeight);
            $outputImage = $workingImage;

            if ($targetWidth !== $workingWidth || $targetHeight !== $workingHeight) {
                $outputImage = imagecreatetruecolor($targetWidth, $targetHeight);
                if (!$outputImage) {
                    throw new \RuntimeException(sprintf('Unable to create target image for "%s".', $filename));
                }

                $this->prepareCanvas($outputImage, $mimeType);

                imagecopyresampled(
                    $outputImage,
                    $workingImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $workingWidth,
                    $workingHeight
                );
            }

            $this->writeImage($outputImage, $path, $mimeType);

            if ($outputImage !== $workingImage) {
                imagedestroy($outputImage);
            }
        } finally {
            if ($workingImage !== $sourceImage) {
                imagedestroy($workingImage);
            }

            imagedestroy($sourceImage);
        }
    }

    private function resolvePath(string $filename): string
    {
        $safeFilename = basename($filename);

        return rtrim($this->uploadDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$safeFilename;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function computeTargetSize(int $width, int $height): array
    {
        if ($width <= $this->maxWidth && $height <= $this->maxHeight) {
            return [$width, $height];
        }

        $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function createImageResource(string $path, string $mimeType): \GdImage|null
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    private function guardSourceDimensions(string $filename, int $width, int $height): void
    {
        $pixelCount = $width * $height;

        if ($width > $this->maxSourceWidth || $height > $this->maxSourceHeight || $pixelCount > $this->maxSourcePixels) {
            throw new \InvalidArgumentException(sprintf(
                'Image "%s" is too large to process safely (%dx%d).',
                $filename,
                $width,
                $height
            ));
        }
    }

    private function normalizeOrientation(\GdImage $image, string $path, string $mimeType): \GdImage
    {
        if ('image/jpeg' !== $mimeType || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        return match ($orientation) {
            3 => $this->rotateImage($image, 180),
            6 => $this->rotateImage($image, 270),
            8 => $this->rotateImage($image, 90),
            default => $image,
        };
    }

    private function rotateImage(\GdImage $image, int $angle): \GdImage
    {
        $rotatedImage = imagerotate($image, $angle, 0);
        if (!$rotatedImage instanceof \GdImage) {
            throw new \RuntimeException('Unable to rotate uploaded image.');
        }

        return $rotatedImage;
    }

    private function prepareCanvas(\GdImage $image, string $mimeType): void
    {
        if (!in_array($mimeType, ['image/png', 'image/webp'], true)) {
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }

    private function writeImage(\GdImage $image, string $path, string $mimeType): void
    {
        $temporaryPath = sprintf('%s/.%s.tmp', dirname($path), uniqid('product-image-', true));

        $written = match ($mimeType) {
            'image/jpeg' => $this->writeJpeg($image, $temporaryPath),
            'image/png' => imagepng($image, $temporaryPath, $this->pngCompression),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, $temporaryPath, $this->webpQuality),
            default => false,
        };

        if (!$written) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Unable to write optimized image "%s".', $path));
        }

        if (!@rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Unable to replace image "%s" after optimization.', $path));
        }
    }

    private function writeJpeg(\GdImage $image, string $path): bool
    {
        imageinterlace($image, true);

        return imagejpeg($image, $path, $this->jpegQuality);
    }
}
