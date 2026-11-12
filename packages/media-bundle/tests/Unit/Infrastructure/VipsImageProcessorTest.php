<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\Transform\Crop;
use Phil\MediaBundle\Application\Transform\Expand;
use Phil\MediaBundle\Application\Transform\Resize;
use Phil\MediaBundle\Application\Transform\Thumbnail;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Image\ImageProcessors;
use Phil\MediaBundle\Infrastructure\Image\VipsImageProcessor;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The commands given to libvips, checked on stand-ins of `vips` and `vipsheader` that log their arguments and copy
 * their input; libvips itself renders when it is installed.
 */
#[CoversClass(VipsImageProcessor::class)]
#[CoversClass(ImageProcessors::class)]
final class VipsImageProcessorTest extends TestCase
{
    private string $bin;

    protected function setUp(): void
    {
        $this->bin = Images::directory();
        file_put_contents($this->bin.'/vips', <<<'SH'
            #!/bin/sh
            here=$(dirname "$0")
            shift_args="$*"
            if [ "$VIPS_BLOCK_UNTRUSTED" != "1" ]; then echo "vips: untrusted loaders open" >&2; exit 1; fi
            if [ "$1" = "colourspace" ]; then ls -ld "$(dirname "$2")" | cut -c1-10 > "$here/mode"; fi
            echo "$shift_args" | sed "s#$TMPDIR##g; s#/phil-media-vips-[0-9a-f]*/##g; s#-[0-9a-f]\{8\}\.v#.v#g" >> "$here/log"
            if [ -f "$here/fail" ] && [ "$(cat "$here/fail")" = "$1" ]; then echo "vips: $1 refused" >&2; exit 1; fi
            if [ -f "$here/empty" ] && [ "$1" = "copy" ]; then exit 0; fi
            out="${3%%[*}"
            cp "$2" "$out"
            SH);
        file_put_contents($this->bin.'/vipsheader', "#!/bin/sh\ncat \"\$(dirname \"\$0\")/bands\" 2>/dev/null || echo 3\n");
        chmod($this->bin.'/vips', 0o755);
        chmod($this->bin.'/vipsheader', 0o755);
    }

    public function testACropIsAnAreaExtractedThenScaled(): void
    {
        $png = Images::png(400, 200);
        $rendered = VipsImageProcessor::of($this->bin.'/vips')->render($png, ImageFormat::of('thumb', 100, 100, quality: 70), [0.5, 0.5], 'webp');

        self::assertSame($png, $rendered, 'the stand-in copies its input');
        self::assertSame([
            'colourspace source colourspace.v srgb',
            'extract_area colourspace.v extract_area.v 100 0 200 200',
            'resize extract_area.v resize.v 0.5 --vscale 0.5',
            'copy resize.v rendered.webp[Q=70,strip]',
        ], $this->log());
    }

    public function testAnImageIsPlacedOnACanvas(): void
    {
        $format = ImageFormat::transforming('padded', [Expand::of(500, 300, background: '#336699')]);
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), $format, [0.5, 0.5], 'jpeg');
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::transforming('clear', [Expand::of(500, 300)]), [0.5, 0.5], 'png');

        self::assertSame([
            'colourspace source colourspace.v srgb',
            'embed colourspace.v embed.v 50 50 500 300 --extend background --background 51 102 153',
            'copy embed.v rendered.jpeg[Q=80,strip]',
            'colourspace source colourspace.v srgb',
            'bandjoin_const colourspace.v bandjoin_const.v 255',
            'embed bandjoin_const.v embed.v 50 50 500 300 --extend background --background 0 0 0 0',
            'copy embed.v rendered.png[strip]',
        ], $this->log());
    }

    public function testATransparentCanvasIsFlattenedInJpeg(): void
    {
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::transforming('clear', [Expand::of(500, 300)]), [0.5, 0.5], 'jpeg');

        self::assertSame([
            'colourspace source colourspace.v srgb',
            'bandjoin_const colourspace.v bandjoin_const.v 255',
            'embed bandjoin_const.v embed.v 50 50 500 300 --extend background --background 0 0 0 0',
            'flatten embed.v flatten.v --background 0 0 0',
            'copy flatten.v rendered.jpeg[Q=80,strip]',
        ], $this->log());
    }

    public function testTheCropFollowsTheFocalPoint(): void
    {
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::of('thumb', 100, 100), [0.0, 1.0], 'png');
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(200, 400), ImageFormat::of('thumb', 100, 100), [1.0, 0.0], 'png');

        self::assertSame(['extract_area colourspace.v extract_area.v 0 0 200 200', 'extract_area colourspace.v extract_area.v 0 0 200 200'], [$this->log()[1], $this->log()[5]]);
    }

    public function testAWholeScaleIsWrittenWithoutAPoint(): void
    {
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(100, 50), ImageFormat::transforming('double', [Resize::of(200, 100, true)]), [0.5, 0.5], 'png');

        self::assertSame('resize colourspace.v resize.v 2 --vscale 2', $this->log()[1]);
    }

    public function testTheWorkingDirectoryIsPrivateAndItsOwn(): void
    {
        $shared = sys_get_temp_dir().'/phil-media-vips-';
        @mkdir($shared);

        try {
            VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(4, 4), ImageFormat::original(), [0.5, 0.5], 'png');
            self::assertDirectoryExists($shared);
        } finally {
            @rmdir($shared);
        }

        self::assertSame('drwx------', trim((string) file_get_contents($this->bin.'/mode')));
    }

    public function testAnImageOfOnePixelAcrossIsRenderedOneOfNoneIsNot(): void
    {
        $processor = VipsImageProcessor::of($this->bin.'/vips');
        self::assertSame(Images::png(1, 5), $processor->render(Images::png(1, 5), ImageFormat::original(), [0.5, 0.5], 'png'));
        self::assertSame(Images::png(5, 1), $processor->render(Images::png(5, 1), ImageFormat::original(), [0.5, 0.5], 'png'));

        foreach ([[0, 5], [5, 0]] as [$width, $height]) {
            $header = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR".pack('NN', $width, $height)."\x08\x02\0\0\0\0\0\0\0";

            try {
                $processor->render($header, ImageFormat::original(), [0.5, 0.5], 'png');
                self::fail($width.' × '.$height);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame('The file cannot be read as an image.', $invalidMedia->getMessage());
            }
        }
    }

    public function testAnImageWithAlphaKeepsItAndLosesItInJpeg(): void
    {
        file_put_contents($this->bin.'/bands', "4\n");
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::transforming('padded', [Expand::of(500, 300, background: '#ffffff')]), [0.5, 0.5], 'jpeg');
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::transforming('clear', [Expand::of(500, 300)]), [0.5, 0.5], 'avif');

        self::assertSame([
            'colourspace source colourspace.v srgb',
            'embed colourspace.v embed.v 50 50 500 300 --extend background --background 255 255 255 255',
            'flatten embed.v flatten.v --background 0 0 0',
            'copy flatten.v rendered.jpeg[Q=80,strip]',
            'colourspace source colourspace.v srgb',
            'embed colourspace.v embed.v 50 50 500 300 --extend background --background 0 0 0 0',
            'copy embed.v rendered.avif[Q=80,strip,compression=av1]',
        ], $this->log());
    }

    public function testAStepThatChangesNothingRunsNothing(): void
    {
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::original(), [0.5, 0.5], 'png');
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::transforming('scaled', [Crop::of(400, 200), Thumbnail::of(200, 100, 'fit')]), [0.5, 0.5], 'png');

        self::assertSame([
            'colourspace source colourspace.v srgb',
            'copy colourspace.v rendered.png[strip]',
            'colourspace source colourspace.v srgb',
            'resize colourspace.v resize.v 0.5 --vscale 0.5',
            'copy resize.v rendered.png[strip]',
        ], $this->log());
    }

    public function testAThirdIsWrittenInFull(): void
    {
        VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(300, 300), ImageFormat::of('third', 100, 100), [0.5, 0.5], 'png');

        self::assertSame('resize colourspace.v resize.v 0.333333333333 --vscale 0.333333333333', $this->log()[1]);
    }

    public function testWhatIsNotARasterImageNeverReachesLibvips(): void
    {
        $processor = VipsImageProcessor::of($this->bin.'/vips', 1000);

        foreach ([
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', 'webp', 'The file cannot be read as an image.'],
            'pdf' => ['%PDF-1.4', 'webp', 'The file cannot be read as an image.'],
            'bmp' => ["BM\x3a\0\0\0\0\0\0\0\x36\0\0\0\x28\0\0\0\x01\0\0\0\x01\0\0\0\x01\0\x18\0\0\0\0\0\x04\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\xff\0", 'webp', 'The file cannot be read as an image.'],
            'too large' => [Images::png(40, 26), 'webp', 'An image of 40 × 26 pixels is larger than the 1000 pixels rendered at most.'],
            'type' => [Images::png(10, 10), 'gif', 'An image is rendered as webp, avif, jpeg or png; not as "gif".'],
        ] as $case => [$image, $type, $message]) {
            try {
                $processor->render($image, ImageFormat::original(), [0.5, 0.5], $type);
                self::fail($case);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage(), $case);
            }
        }

        self::assertSame([], $this->log());
        self::assertSame('PNG', substr(VipsImageProcessor::of($this->bin.'/vips', 1000)->render(Images::png(40, 25), ImageFormat::original(), [0.5, 0.5], 'png'), 1, 3));
        self::assertSame('GIF', substr(VipsImageProcessor::of($this->bin.'/vips')->render(Images::gif(4, 4), ImageFormat::original(), [0.5, 0.5], 'png'), 0, 3));
        self::assertSame("\xff\xd8", substr(VipsImageProcessor::of($this->bin.'/vips')->render(Images::jpeg(4, 4), ImageFormat::original(), [0.5, 0.5], 'png'), 0, 2));
        self::assertSame('RIFF', substr(VipsImageProcessor::of($this->bin.'/vips')->render(Images::webp(4, 4), ImageFormat::original(), [0.5, 0.5], 'png'), 0, 4));
        self::assertSame('ftypavif', substr(VipsImageProcessor::of($this->bin.'/vips')->render(Images::avif(4, 4), ImageFormat::original(), [0.5, 0.5], 'png'), 4, 8));
    }

    public function testAFailureIsReportedAndLeavesNothingBehind(): void
    {
        $before = glob(sys_get_temp_dir().'/phil-media-vips-*');
        file_put_contents($this->bin.'/fail', 'resize');

        try {
            VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(400, 200), ImageFormat::of('thumb', 100, 100), [0.5, 0.5], 'webp');
            self::fail('vips failed');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('vips resize failed: vips: resize refused', $invalidMedia->getMessage());
        }

        unlink($this->bin.'/fail');
        touch($this->bin.'/empty');

        try {
            VipsImageProcessor::of($this->bin.'/vips')->render(Images::png(40, 20), ImageFormat::original(), [0.5, 0.5], 'webp');
            self::fail('no image');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('vips wrote no image.', $invalidMedia->getMessage());
        }

        self::assertSame($before, glob(sys_get_temp_dir().'/phil-media-vips-*'));
    }

    public function testTheBinariesAreFoundOrNamed(): void
    {
        $processor = VipsImageProcessor::find(path: '/nowhere'.\PATH_SEPARATOR.\PATH_SEPARATOR.$this->bin);
        self::assertInstanceOf(VipsImageProcessor::class, $processor);
        self::assertSame([$this->bin.'/vips', $this->bin.'/vipsheader'], [$processor->vips, $processor->header]);
        self::assertNull(VipsImageProcessor::find(path: '/nowhere'));

        $lonely = Images::directory();
        copy($this->bin.'/vips', $lonely.'/vips');
        chmod($lonely.'/vips', 0o755);
        self::assertNull(VipsImageProcessor::find(path: $lonely));

        $headless = Images::directory();
        copy($this->bin.'/vipsheader', $headless.'/vipsheader');
        chmod($headless.'/vipsheader', 0o755);
        self::assertNull(VipsImageProcessor::find(path: $headless));

        $path = getenv('PATH');
        putenv('PATH=/nowhere'.\PATH_SEPARATOR.$this->bin);

        try {
            self::assertSame($this->bin.'/vips', VipsImageProcessor::find()?->vips);
        } finally {
            putenv(false === $path ? 'PATH' : 'PATH='.$path);
        }

        foreach ([$lonely.'/vips' => $lonely.'/vipsheader', '/nowhere/vips' => '/nowhere/vips', $this->bin.'/log-missing' => $this->bin.'/log-missing'] as $vips => $missing) {
            try {
                VipsImageProcessor::of($vips);
                self::fail($vips);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame(\sprintf('"%s" is not an executable file.', $missing), $invalidMedia->getMessage());
            }
        }

        touch($lonely.'/vipsheader');
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s/vipsheader" is not an executable file.', $lonely));
        VipsImageProcessor::of($lonely.'/vips');
    }

    public function testTheConfigurationChoosesTheProcessor(): void
    {
        $gd = new GdImageProcessor();

        self::assertSame($gd, ImageProcessors::choose('gd', $this->bin.'/vips', 100, $gd));
        self::assertSame($gd, ImageProcessors::choose('auto', null, 100, $gd, '/nowhere'));
        self::assertInstanceOf(VipsImageProcessor::class, ImageProcessors::choose('auto', null, 100, $gd, $this->bin));
        $named = ImageProcessors::choose('vips', $this->bin.'/vips', 100, $gd, '/nowhere');
        self::assertInstanceOf(VipsImageProcessor::class, $named);
        self::assertSame($this->bin.'/vips', $named->vips);

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The image processor is vips, and no vips was found on the PATH: install libvips, or name its path in phil_media.vips_binary.');
        ImageProcessors::choose('vips', null, 100, $gd, '/nowhere');
    }

    public function testLibvipsRendersWhenItIsInstalled(): void
    {
        $processor = VipsImageProcessor::find();

        if (!$processor instanceof VipsImageProcessor) {
            self::markTestSkipped('libvips is not installed.');
        }

        foreach (['webp', 'avif', 'jpeg', 'png'] as $type) {
            $size = getimagesizefromstring($processor->render(Images::png(400, 200), ImageFormat::transforming('card', [Crop::of(300, 200), Expand::of(320, 220, background: '#ffffff')]), [0.25, 0.5], $type));
            self::assertIsArray($size, $type);
            self::assertSame([320, 220, 'image/'.$type], [$size[0], $size[1], $size['mime']], $type);
        }
    }

    /**
     * @return list<string>
     */
    private function log(): array
    {
        $log = @file($this->bin.'/log', \FILE_IGNORE_NEW_LINES);

        return false === $log ? [] : array_map(fn (string $line): string => str_replace($this->bin, '', $line), $log);
    }
}
