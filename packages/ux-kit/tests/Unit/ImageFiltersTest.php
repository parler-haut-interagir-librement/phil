<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use InvalidArgumentException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Phil\UxKit\Infrastructure\LiipImagine\ImageFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageFilters::class)]
final class ImageFiltersTest extends TestCase
{
    public function testTheSetsOfAnImageBecomeASetOfWidthsSortedByWidth(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_960/uploads/hall.jpg',
            'srcset' => 'https://www.example.org/media/cache/hall_480/uploads/hall.jpg 480w, https://www.example.org/media/cache/hall_960/uploads/hall.jpg 960w',
            'sources' => [],
            'width' => 960,
            'height' => 640,
        ], $this->filters()->of('uploads/hall.jpg', ['hall_960', 'hall_480']));
    }

    public function testTheSetsOfAvifAndWebpBecomeTheSourcesInThatOrder(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_960/uploads/hall.jpg',
            'srcset' => 'https://www.example.org/media/cache/hall_480/uploads/hall.jpg 480w, https://www.example.org/media/cache/hall_960/uploads/hall.jpg 960w',
            'sources' => [
                ['type' => 'image/avif', 'srcset' => 'https://www.example.org/media/cache/hall_avif_480/uploads/hall.jpg 480w, https://www.example.org/media/cache/hall_avif_960/uploads/hall.jpg 960w'],
                ['type' => 'image/webp', 'srcset' => 'https://www.example.org/media/cache/hall_webp_960/uploads/hall.jpg'],
            ],
            'width' => 960,
            'height' => 640,
        ], $this->filters()->of('uploads/hall.jpg', ['hall_webp_960', 'hall_480', 'hall_avif_960', 'hall_960', 'hall_avif_480']));
    }

    public function testOneSetIsTheSourceAloneWithoutASet(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_960/uploads/hall.jpg',
            'sources' => [],
            'width' => 960,
            'height' => 640,
        ], $this->filters()->of('uploads/hall.jpg', ['hall_960']));
    }

    public function testWebpIsTheFallbackWhenEverySetWritesAModernFormat(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_webp_960/uploads/hall.jpg',
            'sources' => [['type' => 'image/avif', 'srcset' => 'https://www.example.org/media/cache/hall_avif_960/uploads/hall.jpg']],
            'width' => 960,
            'height' => 640,
        ], $this->filters()->of('uploads/hall.jpg', ['hall_avif_960', 'hall_webp_960']));
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_avif_960/uploads/hall.jpg',
            'sources' => [],
            'width' => 960,
            'height' => 640,
        ], $this->filters()->of('uploads/hall.jpg', ['hall_avif_960']), 'AVIF alone is its own fallback');
    }

    public function testASetWithoutFormatTakesThatOfThePath(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_480/uploads/hall.webp',
            'sources' => [],
            'width' => 480,
            'height' => 320,
        ], $this->filters()->of('uploads/hall.webp', ['hall_480']), 'a WebP image is its own fallback');
    }

    public function testTheFirstOtherFormatIsTheFallback(): void
    {
        self::assertSame([
            'src' => 'https://www.example.org/media/cache/hall_png/uploads/hall.jpg',
            'sources' => [],
        ], $this->filters()->of('uploads/hall.jpg', ['hall_png', 'hall_480']), 'the first other format seen, PNG');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function widths(): iterable
    {
        yield 'thumbnail' => ['hall_480', 480];
        yield 'resize' => ['resized', 300];
        yield 'scale' => ['scaled', 200];
        yield 'downscale' => ['downscaled', 1200];
        yield 'fixed' => ['fixed', 640];
        yield 'relative_resize widened' => ['widened', 720];
        yield 'the last resizing filter' => ['twice', 250];
        yield 'past filters whose options are no map' => ['strip_first', 300];
        yield 'past a width of zero' => ['zero_after', 300];
        yield 'past a width that is no integer' => ['digits_after', 300];
    }

    #[DataProvider('widths')]
    public function testTheWidthIsReadFromTheLastResizingFilter(string $set, int $width): void
    {
        self::assertSame(
            'https://www.example.org/media/cache/'.$set.'/a.jpg '.$width.'w, https://www.example.org/media/cache/huge/a.jpg 5000w',
            $this->filters()->of('a.jpg', [$set, 'huge' => 5000])['srcset'] ?? null,
        );
    }

    public function testAWidthGivenWinsOverTheFilters(): void
    {
        self::assertSame(
            'https://www.example.org/media/cache/hall_960/a.jpg 100w, https://www.example.org/media/cache/hall_480/a.jpg 2000w',
            $this->filters()->of('a.jpg', ['hall_480' => 2000, 'hall_960' => 100])['srcset'] ?? null,
        );
    }

    public function testAWidthOfOneIsAWidth(): void
    {
        self::assertSame('https://www.example.org/media/cache/grey/a.jpg 1w, https://www.example.org/media/cache/huge/a.jpg 5000w', $this->filters()->of('a.jpg', ['grey' => 1, 'huge' => 5000])['srcset'] ?? null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inexactSizes(): iterable
    {
        yield 'a thumbnail inset in a box' => ['inset'];
        yield 'a crop then a resize that changes its height' => ['cropped_then_widened'];
        yield 'a thumbnail without a height' => ['no_height'];
        yield 'a thumbnail of no width' => ['zero_width'];
        yield 'a fixed size of no height' => ['zero_height'];
        yield 'a width that is no integer' => ['digits_width'];
        yield 'a height that is no integer' => ['digits_height'];
        yield 'a thumbnail whose size is no pair' => ['size_text'];
        yield 'a crop then a resize' => ['fixed_then_resize'];
        yield 'a crop then a scale' => ['fixed_then_scale'];
        yield 'a crop then a downscale' => ['fixed_then_downscale'];
    }

    #[DataProvider('inexactSizes')]
    public function testAnInexactSizeIsNotGiven(string $set): void
    {
        self::assertSame([], array_intersect_key($this->filters()->of('a.jpg', [$set => 100]), ['width' => 0, 'height' => 0]));
    }

    public function testAFilterThatDoesNotResizeKeepsTheSize(): void
    {
        self::assertSame(['width' => 640, 'height' => 480], array_intersect_key($this->filters()->of('a.jpg', ['fixed_then_grey']), ['width' => 0, 'height' => 0]));
        self::assertSame(['width' => 640, 'height' => 480], array_intersect_key($this->filters()->of('a.jpg', ['fixed_then_text_thumbnail' => 640]), ['width' => 0, 'height' => 0]), 'a filter whose options are no map is skipped');
    }

    public function testTheSizeIsGivenOnlyWhenTheWidestSetCropsExactly(): void
    {
        self::assertSame(['width' => 640, 'height' => 480], array_intersect_key($this->filters()->of('a.jpg', ['fixed']), ['width' => 0, 'height' => 0]), 'fixed');
        self::assertSame([], array_intersect_key($this->filters()->of('a.jpg', ['inset']), ['width' => 0, 'height' => 0]), 'a thumbnail inset in a box');
        self::assertSame([], array_intersect_key($this->filters()->of('a.jpg', ['cropped_then_widened']), ['width' => 0, 'height' => 0]), 'a crop then a resize that changes its height');
        self::assertSame([], array_intersect_key($this->filters()->of('a.jpg', ['no_height']), ['width' => 0, 'height' => 0]), 'a thumbnail without a height');
    }

    public function testNoSetIsAnError(): void
    {
        try {
            $this->filters()->of('a.jpg', []);
            self::fail('Nothing was refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('No filter set for "a.jpg": give the names of the sets of its candidates.', $invalidArgumentException->getMessage());
        }
    }

    public function testAnUnknownSetIsAnError(): void
    {
        try {
            $this->filters()->of('a.jpg', ['missing']);
            self::fail('Nothing was refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('LiipImagine has no filter set "missing".', $invalidArgumentException->getMessage());
        }
    }

    public function testASetNamedByNothingIsAnError(): void
    {
        try {
            $this->filters()->of('a.jpg', ['']);
            self::fail('Nothing was refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('A filter set is named by a string.', $invalidArgumentException->getMessage());
        }
    }

    public function testASetWhoseWidthIsReadNowhereIsAnError(): void
    {
        try {
            $this->filters()->of('a.jpg', ['grey']);
            self::fail('Nothing was refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('The width of the filter set "grey" is read from none of its filters: give it, {grey: 480}.', $invalidArgumentException->getMessage());
        }
    }

    public function testAWidthBelowOneIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->filters()->of('a.jpg', ['grey' => 0]);
    }

    private function filters(): ImageFilters
    {
        $cache = self::createStub(CacheManager::class);
        $cache->method('getBrowserPath')->willReturnCallback(static fn (string $path, string $filter): string => 'https://www.example.org/media/cache/'.$filter.'/'.$path);

        return new ImageFilters($cache, new FilterConfiguration([
            'hall_480' => ['filters' => ['thumbnail' => ['size' => [480, 320], 'mode' => 'outbound']]],
            'hall_960' => ['filters' => ['thumbnail' => ['size' => [960, 640], 'mode' => 'outbound']]],
            'hall_avif_480' => ['format' => 'avif', 'filters' => ['thumbnail' => ['size' => [480, 320], 'mode' => 'outbound']]],
            'hall_avif_960' => ['format' => 'AVIF', 'filters' => ['thumbnail' => ['size' => [960, 640], 'mode' => 'outbound']]],
            'hall_webp_960' => ['format' => 'webp', 'filters' => ['thumbnail' => ['size' => [960, 640], 'mode' => 'outbound']]],
            'hall_png' => ['format' => 'png', 'filters' => ['thumbnail' => ['size' => [100, 100]]]],
            'resized' => ['format' => '', 'filters' => ['resize' => ['size' => [300, 200]]]],
            'scaled' => ['filters' => ['scale' => ['dim' => [200, 100]]]],
            'downscaled' => ['filters' => ['downscale' => ['max' => [1200, 800]]]],
            'fixed' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480]]],
            'widened' => ['filters' => ['relative_resize' => ['widen' => 720]]],
            'twice' => ['filters' => ['thumbnail' => ['size' => [500, 500]], 'grayscale' => [], 'downscale' => ['max' => [250, 250]], 'strip' => []]],
            'inset' => ['filters' => ['thumbnail' => ['size' => [400, 300], 'mode' => 'inset']]],
            'cropped_then_widened' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'relative_resize' => ['widen' => 1280]]],
            'no_height' => ['filters' => ['thumbnail' => ['size' => [400], 'mode' => 'outbound']]],
            'huge' => ['filters' => ['strip' => []]],
            'strip_first' => ['filters' => ['strip' => 'yes', 'thumbnail' => ['size' => [300, 300]], 'interlace' => 'line']],
            'zero_after' => ['filters' => ['thumbnail' => ['size' => [300, 300]], 'downscale' => ['max' => [0, 0]]]],
            'digits_after' => ['filters' => ['thumbnail' => ['size' => [300, 300]], 'relative_resize' => ['widen' => '720']]],
            'zero_width' => ['filters' => ['thumbnail' => ['size' => [0, 100], 'mode' => 'outbound']]],
            'zero_height' => ['filters' => ['fixed' => ['width' => 640, 'height' => 0]]],
            'digits_width' => ['filters' => ['fixed' => ['width' => '640', 'height' => 480]]],
            'digits_height' => ['filters' => ['fixed' => ['width' => 640, 'height' => '480']]],
            'size_text' => ['filters' => ['thumbnail' => ['size' => '640x480', 'mode' => 'outbound']]],
            'fixed_then_resize' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'resize' => ['size' => [320, 200]]]],
            'fixed_then_scale' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'scale' => ['to' => 0.5]]],
            'fixed_then_downscale' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'downscale' => ['by' => 0.5]]],
            'fixed_then_text_thumbnail' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'thumbnail' => 'oops']],
            'fixed_then_grey' => ['filters' => ['fixed' => ['width' => 640, 'height' => 480], 'grayscale' => [], 'strip' => 'yes']],
            'grey' => ['filters' => ['grayscale' => [], 'thumbnail' => 'oops']],
        ]));
    }
}
