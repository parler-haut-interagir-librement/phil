<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use GdImage;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\Transform\Crop;
use Phil\MediaBundle\Application\Transform\Expand;
use Phil\MediaBundle\Application\Transform\Heighten;
use Phil\MediaBundle\Application\Transform\Resize;
use Phil\MediaBundle\Application\Transform\Widen;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Image\BinaryEncoder;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GdImageProcessor::class)]
#[CoversClass(BinaryEncoder::class)]
final class GdImageProcessorTest extends TestCase
{
    public function testACropKeepsTheFocalPointInView(): void
    {
        $processor = new GdImageProcessor();
        $png = Images::png(400, 200);
        $square = ImageFormat::of('square', 100, 100);

        $left = $processor->render($png, $square, [0.0, 0.5], 'png');
        $right = $processor->render($png, $square, [1.0, 0.5], 'png');
        $middle = $processor->render($png, $square, [0.5, 0.5], 'png');

        self::assertSame([100, 100], $this->dimensions($left));
        self::assertSame([[255, 0, 0], [0, 0, 255]], [Images::color($middle, 0, 0), Images::color($middle, 99, 99)]);
        self::assertSame([[255, 0, 0], [255, 0, 0]], [Images::color($left, 5, 50), Images::color($left, 95, 50)]);
        self::assertSame([[0, 0, 255], [0, 0, 255]], [Images::color($right, 5, 50), Images::color($right, 95, 50)]);
        self::assertSame([[255, 0, 0], [0, 0, 255]], [Images::color($middle, 5, 50), Images::color($middle, 95, 50)]);
        self::assertSame([[255, 0, 0], [0, 0, 255]], [Images::color($processor->render($png, $square, [0.3, 0.5], 'png'), 70, 50), Images::color($processor->render($png, $square, [0.7, 0.5], 'png'), 30, 50)]);

        $tall = Images::png(200, 400);
        self::assertSame([100, 100], $this->dimensions($processor->render($tall, $square, [0.5, 0.0], 'png')));
        $stacked = $this->stacked(200, 400);
        $top = $processor->render($stacked, $square, [0.5, 0.0], 'png');
        $bottom = $processor->render($stacked, $square, [0.5, 1.0], 'png');
        self::assertSame([[255, 0, 0], [255, 0, 0]], [Images::color($top, 50, 5), Images::color($top, 50, 95)], 'The vertical focal point keeps the top in view.');
        self::assertSame([[0, 0, 255], [0, 0, 255]], [Images::color($bottom, 50, 5), Images::color($bottom, 50, 95)]);
        self::assertSame([1, 1], $this->dimensions($processor->render(Images::png(1, 10), ImageFormat::of('a', 10, 10), [0.5, 0.5], 'png')), 'An image one pixel wide is cropped to one pixel.');
        self::assertSame([1, 1], $this->dimensions($processor->render(Images::png(10, 1), ImageFormat::of('a', 10, 10), [0.5, 0.5], 'png')), 'An image one pixel high is cropped to one pixel.');
        self::assertSame([200, 100], $this->dimensions($processor->render($png, ImageFormat::of('banner', 200, 100), [0.5, 0.5], 'png')));
        self::assertSame([400, 200], $this->dimensions($processor->render($png, ImageFormat::of('huge', 800, 400), [0.5, 0.5], 'png')));
        self::assertSame([200, 200], $this->dimensions($processor->render($png, ImageFormat::of('big', 800, 800), [0.5, 0.5], 'png')));
        self::assertSame([[0, 0, 255]], [Images::color($processor->render($png, ImageFormat::of('big', 800, 800), [1.0, 0.5], 'png'), 199, 100)]);
    }

    public function testAFitStaysWhole(): void
    {
        $processor = new GdImageProcessor();
        $png = Images::png(400, 200);

        $fitted = $processor->render($png, ImageFormat::of('fit', 100, 100, 'fit'), [0.0, 0.0], 'png');
        self::assertSame([100, 50], $this->dimensions($fitted));
        self::assertSame([[255, 0, 0], [0, 0, 255]], [Images::color($fitted, 5, 25), Images::color($fitted, 95, 25)]);
        self::assertSame([400, 200], $this->dimensions($processor->render($png, ImageFormat::of('fit', 1000, 1000, 'fit'), [0.5, 0.5], 'png')));
        self::assertSame([1, 1], $this->dimensions($processor->render(Images::png(1000, 2), ImageFormat::of('fit', 1, 1, 'fit'), [0.5, 0.5], 'png')));
        self::assertSame([200, 400], $this->dimensions($processor->render(Images::png(400, 800), ImageFormat::of('fit', 400, 400, 'fit'), [0.5, 0.5], 'png')));
    }

    public function testEachFileType(): void
    {
        $processor = new GdImageProcessor();
        $png = Images::png(40, 20);
        $format = ImageFormat::of('a', 20, 10, 'crop', 70);

        self::assertSame(['RIFF', 'WEBP'], [substr($processor->render($png, $format, [0.5, 0.5], 'webp'), 0, 4), substr($processor->render($png, $format, [0.5, 0.5], 'webp'), 8, 4)]);
        self::assertSame('ftypavif', substr($processor->render($png, $format, [0.5, 0.5], 'avif'), 4, 8));
        self::assertSame("\xFF\xD8", substr($processor->render($png, $format, [0.5, 0.5], 'jpeg'), 0, 2));
        self::assertSame("\x89PNG", substr($processor->render($png, $format, [0.5, 0.5], 'png'), 0, 4));
        self::assertSame(\strlen($processor->render(Images::png(400, 400), ImageFormat::of('a', 400, 400, 'crop', 100), [0.5, 0.5], 'png')), \strlen($processor->render(Images::png(400, 400), ImageFormat::of('a', 400, 400, 'crop', 1), [0.5, 0.5], 'png')));
        self::assertLessThan(\strlen($processor->render(Images::png(400, 400), ImageFormat::of('a', 400, 400, 'crop', 100), [0.5, 0.5], 'jpeg')), \strlen($processor->render(Images::png(400, 400), ImageFormat::of('a', 400, 400, 'crop', 1), [0.5, 0.5], 'jpeg')));

        $transparent = Images::canvas(10, 10);
        imagealphablending($transparent, false);
        imagesavealpha($transparent, true);
        imagefill($transparent, 0, 0, (int) imagecolorallocatealpha($transparent, 0, 255, 0, 64));
        ob_start();
        imagepng($transparent);
        $kept = imagecreatefromstring($processor->render((string) ob_get_clean(), ImageFormat::of('a', 10, 10), [0.5, 0.5], 'png'));
        self::assertInstanceOf(GdImage::class, $kept);
        self::assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 64], imagecolorsforindex($kept, (int) imagecolorat($kept, 5, 5)));

        try {
            $processor->render($png, $format, [0.5, 0.5], 'gif');
            self::fail('A GIF was rendered.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('An image is rendered as webp, avif, jpeg or png; not as "gif".', $invalidMedia->getMessage());
        }

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The file cannot be read as an image.');

        $processor->render('not an image', $format, [0.5, 0.5], 'png');
    }

    public function testAnImageLargerThanThePixelLimitIsNotDecoded(): void
    {
        $format = ImageFormat::of('a', 20, 10);

        self::assertSame([20, 10], $this->dimensions(new GdImageProcessor(800)->render(Images::png(40, 20), $format, [0.5, 0.5], 'png')));
        self::assertSame(25_000_000, GdImageProcessor::MAX_PIXELS);

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('An image of 40 × 21 pixels is larger than the 800 pixels rendered at most.');

        new GdImageProcessor(800)->render(Images::png(40, 21), $format, [0.5, 0.5], 'png');
    }

    public function testTransformationsFollowEachOther(): void
    {
        $processor = new GdImageProcessor();
        $png = Images::png(400, 200);

        $card = $processor->render($png, ImageFormat::transforming('card', [Crop::of(200, 200), Widen::of(100), Expand::of(100, 140, background: '#00ff00')]), [0.75, 0.5], 'png');
        self::assertSame([100, 140], $this->dimensions($card));
        self::assertSame([[0, 255, 0], [0, 0, 255], [0, 255, 0]], [Images::color($card, 50, 5), Images::color($card, 50, 70), Images::color($card, 50, 135)]);
        self::assertSame([[0, 255, 0], [0, 255, 0]], [Images::color($card, 99, 0), Images::color($card, 0, 139)]);

        $margin = imagecreatefromstring($processor->render($png, ImageFormat::transforming('margin', [Expand::of(400, 220)]), [0.5, 0.5], 'png'));
        self::assertInstanceOf(GdImage::class, $margin);
        self::assertSame(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 127], imagecolorsforindex($margin, (int) imagecolorat($margin, 0, 0)));
        self::assertSame(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 127], imagecolorsforindex($margin, (int) imagecolorat($margin, 399, 219)), 'The margin reaches the last column and the last row.');
        self::assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], imagecolorsforindex($margin, (int) imagecolorat($margin, 0, 10)));

        $stretched = $processor->render($png, ImageFormat::transforming('s', [Resize::of(50, 100), Heighten::of(50)]), [0.5, 0.5], 'png');
        self::assertSame([25, 50], $this->dimensions($stretched));
        self::assertSame([400, 200], $this->dimensions($processor->render($png, ImageFormat::original(), [0.5, 0.5], 'png')));
        self::assertSame([[255, 0, 0], [0, 0, 255]], [Images::color($processor->render($png, ImageFormat::original(), [0.5, 0.5], 'png'), 0, 0), Images::color($processor->render($png, ImageFormat::original(), [0.5, 0.5], 'png'), 399, 199)]);
    }

    public function testBinariesEncodeWebpAndAvif(): void
    {
        $webp = BinaryEncoder::find('webp');
        $avif = BinaryEncoder::find('avif');

        if (!$webp instanceof BinaryEncoder || !$avif instanceof BinaryEncoder) {
            self::markTestSkipped('cwebp and avifenc are not installed.');
        }

        self::assertSame(['webp', 'cwebp', 'avif', 'avifenc'], [$webp->type, basename($webp->binary), $avif->type, basename($avif->binary)]);
        $processor = new GdImageProcessor(encoders: ['webp' => $webp, 'avif' => $avif]);
        $png = Images::png(40, 20);
        $format = ImageFormat::of('a', 20, 10, 'crop', 70);

        $encoded = $processor->render($png, $format, [0.5, 0.5], 'webp');
        self::assertSame(['RIFF', 'WEBP', [20, 10]], [substr($encoded, 0, 4), substr($encoded, 8, 4), $this->dimensions($encoded)]);
        self::assertSame('ftypavif', substr($processor->render($png, $format, [0.5, 0.5], 'avif'), 4, 8));
        self::assertSame("\xFF\xD8", substr($processor->render($png, $format, [0.5, 0.5], 'jpeg'), 0, 2));
        self::assertLessThan(\strlen($webp->encode(Images::noise(64, 64), 100)), \strlen($webp->encode(Images::noise(64, 64), 1)));
        self::assertLessThan(\strlen($avif->encode(Images::noise(64, 64), 100)), \strlen($avif->encode(Images::noise(64, 64), 1)));
    }

    public function testABinaryRunsOnTwoFilesOfAPrivateDirectory(): void
    {
        $directory = Images::directory();
        $log = $directory.'/log.json';
        $script = $directory.'/cwebp';
        file_put_contents($script, '#!'.\PHP_BINARY."\n<?php\n\$in = \$argv[\$argc - (in_array('-o', \$argv, true) ? 3 : 2)];\nfile_put_contents('{$log}', json_encode(['args' => array_slice(\$argv, 1), 'mode' => fileperms(dirname(\$in)) & 0777, 'input' => hash_file('sha256', \$in)]));\nfile_put_contents(\$argv[\$argc - 1], 'encoded');\n");
        chmod($script, 0o755);
        $png = Images::png(4, 4);

        foreach (['webp' => ['-quiet', '-q', '42', '-alpha_q', '100', '-metadata', 'none', 'IN', '-o', 'OUT'], 'avif' => ['-q', '42', '-s', '6', '--ignore-exif', '--ignore-xmp', 'IN', 'OUT']] as $type => $arguments) {
            self::assertSame('encoded', BinaryEncoder::of($type, $script)->encode($png, 42));
            $run = json_decode((string) file_get_contents($log), true);
            self::assertIsArray($run);
            self::assertIsArray($run['args']);
            $input = $run['args'][\count($arguments) - ('webp' === $type ? 3 : 2)];
            $output = $run['args'][\count($arguments) - 1];
            self::assertIsString($input);
            self::assertIsString($output);
            self::assertMatchesRegularExpression('{^'.preg_quote(sys_get_temp_dir(), '{').'/phil-media-encoding-[0-9a-f]{16}/in\.png$}D', $input);
            self::assertSame(\dirname($input).'/out.'.$type, $output);
            self::assertSame(str_replace(['IN', 'OUT'], [$input, $output], $arguments), $run['args']);
            self::assertSame([0o700, hash('sha256', $png)], [$run['mode'], $run['input']]);
            self::assertDirectoryDoesNotExist(\dirname($input));
        }

        $path = getenv('PATH');
        self::assertIsString($path);
        self::assertSame(BinaryEncoder::find('webp', $path)?->binary, BinaryEncoder::find('webp')?->binary);
    }

    /**
     * With an encoder for the type, GD draws a PNG that the encoder turns into the file returned.
     */
    public function testAnEncoderCompressesThePngGdDraws(): void
    {
        $directory = Images::directory();
        $log = $directory.'/input.png';
        $script = $directory.'/cwebp';
        file_put_contents($script, '#!'.\PHP_BINARY."\n<?php\ncopy(\$argv[\$argc - 3], '{$log}');\nfile_put_contents(\$argv[\$argc - 1], 'encoded');\n");
        chmod($script, 0o755);
        $processor = new GdImageProcessor(encoders: ['webp' => BinaryEncoder::of('webp', $script)]);

        self::assertSame('encoded', $processor->render(Images::png(40, 20), ImageFormat::of('a', 20, 10), [0.5, 0.5], 'webp'));
        $drawn = getimagesize($log);
        self::assertIsArray($drawn);
        self::assertSame([20, 10, \IMAGETYPE_PNG], [$drawn[0], $drawn[1], $drawn[2]]);
        self::assertSame("\xFF\xD8", substr($processor->render(Images::png(40, 20), ImageFormat::of('a', 20, 10), [0.5, 0.5], 'jpeg'), 0, 2), 'A type without an encoder is drawn by GD.');
    }

    public function testABinaryIsFoundOnThePathOrNamed(): void
    {
        $directory = Images::directory();
        $script = $directory.'/cwebp';
        file_put_contents($script, "#!/bin/sh\necho 'no luck' >&2\nexit 3\n");
        chmod($script, 0o755);
        mkdir($directory.'/avifenc');

        $found = BinaryEncoder::find('webp', '/nowhere'.\PATH_SEPARATOR.\PATH_SEPARATOR.$directory);
        self::assertNotNull($found);
        self::assertSame($script, $found->binary);
        self::assertNull(BinaryEncoder::find('avif', $directory));
        self::assertNull(BinaryEncoder::find('webp', '/nowhere'));
        self::assertNull(BinaryEncoder::find('webp', ''));
        self::assertSame(['webp' => 'cwebp', 'avif' => 'avifenc'], BinaryEncoder::BINARIES);
        self::assertSame(['avif', $script], [BinaryEncoder::of('avif', $script)->type, BinaryEncoder::of('avif', $script)->binary]);

        try {
            $found->encode(Images::png(4, 4), 50);
            self::fail('The script encoded.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('cwebp could not encode the image: no luck', $invalidMedia->getMessage());
        }

        $silent = $directory.'/silent';
        file_put_contents($silent, "#!/bin/sh\nexit 0\n");
        chmod($silent, 0o755);

        foreach ([
            [static fn (): BinaryEncoder => BinaryEncoder::of('gif', $script), 'A binary encodes webp or avif; not "gif".'],
            [static fn (): ?BinaryEncoder => BinaryEncoder::find('gif', $directory), 'A binary encodes webp or avif; not "gif".'],
            [static fn (): BinaryEncoder => BinaryEncoder::of('webp', $directory.'/avifenc'), \sprintf('"%s/avifenc" is not an executable file.', $directory)],
            [static fn (): BinaryEncoder => BinaryEncoder::of('webp', $directory.'/none'), \sprintf('"%s/none" is not an executable file.', $directory)],
            [static fn (): BinaryEncoder => BinaryEncoder::of('webp', Images::file('text')), 'is not an executable file.'],
            [static fn (): string => BinaryEncoder::of('avif', $silent)->encode(Images::png(4, 4), 50), 'silent could not encode the image: '],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertStringContainsString($message, $invalidMedia->getMessage());
            }
        }
    }

    /**
     * A PNG red on its top half and blue on its bottom half.
     *
     * @param int<1, max> $width
     * @param int<2, max> $height
     */
    private function stacked(int $width, int $height): string
    {
        $image = Images::canvas($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, intdiv($height, 2) - 1, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 0, intdiv($height, 2), $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $image): array
    {
        $size = getimagesizefromstring($image);
        self::assertIsArray($size);

        return [$size[0], $size[1]];
    }
}
