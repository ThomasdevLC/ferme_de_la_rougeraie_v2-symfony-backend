<?php

namespace App\Tests\Unit\Service;

use App\Service\Image\ProductImageProcessor;
use PHPUnit\Framework\TestCase;

class ProductImageProcessorTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for image processing tests.');
        }

        $this->uploadDir = sys_get_temp_dir().'/product-image-processor-'.uniqid('', true);
        mkdir($this->uploadDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->uploadDir)) {
            return;
        }

        foreach (glob($this->uploadDir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->uploadDir);
    }

    public function testJpegIsProcessedWithoutError(): void
    {
        $processor = $this->createProcessor();
        $filename = 'product.jpg';
        $path = $this->uploadDir.'/'.$filename;

        $image = imagecreatetruecolor(1200, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 180, 80));
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        $processor->optimize($filename);

        self::assertFileExists($path);
        self::assertSame('image/jpeg', mime_content_type($path));
        self::assertGreaterThan(0, filesize($path));
    }

    public function testPngTransparencyIsPreserved(): void
    {
        $processor = $this->createProcessor();
        $filename = 'transparent.png';
        $path = $this->uploadDir.'/'.$filename;

        $image = imagecreatetruecolor(300, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        $semiTransparentRed = imagecolorallocatealpha($image, 255, 0, 0, 64);
        imagefilledellipse($image, 150, 150, 120, 120, $semiTransparentRed);
        imagepng($image, $path);
        imagedestroy($image);

        $processor->optimize($filename);

        $processedImage = imagecreatefrompng($path);
        $topLeftPixel = imagecolorat($processedImage, 0, 0);
        $topLeftAlpha = ($topLeftPixel >> 24) & 0x7F;
        imagedestroy($processedImage);

        self::assertSame('image/png', mime_content_type($path));
        self::assertSame(127, $topLeftAlpha);
    }

    public function testSmallImageIsNotUpscaled(): void
    {
        $processor = $this->createProcessor();
        $filename = 'small.jpg';
        $path = $this->uploadDir.'/'.$filename;

        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 60));
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        $processor->optimize($filename);

        [$width, $height] = getimagesize($path);

        self::assertSame(320, $width);
        self::assertSame(240, $height);
    }

    public function testLargeImageIsResized(): void
    {
        $processor = $this->createProcessor();
        $filename = 'large.jpg';
        $path = $this->uploadDir.'/'.$filename;

        $image = imagecreatetruecolor(3200, 2400);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 40));
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        $processor->optimize($filename);

        [$width, $height] = getimagesize($path);

        self::assertSame(1600, $width);
        self::assertSame(1200, $height);
    }

    public function testImageExceedingSourceLimitsIsRejected(): void
    {
        $processor = $this->createProcessor();
        $filename = 'too-wide.jpg';
        $path = $this->uploadDir.'/'.$filename;

        $image = imagecreatetruecolor(8100, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 10, 10));
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        $this->expectException(\InvalidArgumentException::class);

        $processor->optimize($filename);
    }

    private function createProcessor(): ProductImageProcessor
    {
        return new ProductImageProcessor(
            uploadDir: $this->uploadDir,
            maxWidth: 1600,
            maxHeight: 1600,
            maxSourceWidth: 8000,
            maxSourceHeight: 8000,
            maxSourcePixels: 40000000,
            jpegQuality: 82,
            pngCompression: 8,
            webpQuality: 82,
        );
    }
}
