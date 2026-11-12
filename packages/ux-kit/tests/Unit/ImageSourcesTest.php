<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use InvalidArgumentException;
use Override;
use Phil\UxKit\Application\ImageSources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

#[CoversClass(ImageSources::class)]
final class ImageSourcesTest extends TestCase
{
    private const string IMAGES = __DIR__.'/Fixtures/images';

    public function testTheFormatsAndDensitiesOfAnImageBecomeTheSourcesOfAPicture(): void
    {
        $sources = new ImageSources($this->assets(['images/logo.avif', 'images/logo@2x.avif', 'images/logo.webp', 'images/logo@2x.webp', 'images/logo.png', 'images/logo@2x.png']));

        self::assertSame([
            'src' => '/assets/images/logo-v1.png',
            'srcset' => '/assets/images/logo-v1.png 1x, /assets/images/logo@2x-v1.png 2x',
            'sources' => [
                ['type' => 'image/avif', 'srcset' => '/assets/images/logo-v1.avif 1x, /assets/images/logo@2x-v1.avif 2x'],
                ['type' => 'image/webp', 'srcset' => '/assets/images/logo-v1.webp 1x, /assets/images/logo@2x-v1.webp 2x'],
            ],
            'width' => 160,
            'height' => 40,
        ], $sources->of('images/logo'));
    }

    public function testAFormatWithOneFileIsWrittenWithoutDescriptor(): void
    {
        $sources = new ImageSources($this->assets(['images/logo.webp', 'images/logo.png', 'images/logo@3x.png']));

        self::assertSame([
            'src' => '/assets/images/logo-v1.png',
            'srcset' => '/assets/images/logo-v1.png 1x, /assets/images/logo@3x-v1.png 3x',
            'sources' => [['type' => 'image/webp', 'srcset' => '/assets/images/logo-v1.webp']],
            'width' => 160,
            'height' => 40,
        ], $sources->of('images/logo'));
    }

    public function testWidthsAreReadWhenAsked(): void
    {
        $sources = new ImageSources($this->assets(['images/hall@480w.avif', 'images/hall@960w.avif', 'images/hall.jpg', 'images/hall@480w.jpg']));

        self::assertSame([
            'src' => '/assets/images/hall-v1.jpg',
            'srcset' => '/assets/images/hall@480w-v1.jpg 480w',
            'sources' => [['type' => 'image/avif', 'srcset' => '/assets/images/hall@480w-v1.avif 480w, /assets/images/hall@960w-v1.avif 960w']],
            'width' => 96,
            'height' => 64,
        ], $sources->of('images/hall', ['widths' => [480, 960]]));
    }

    public function testTheFormatsAndTheFallbacksCanBeChosen(): void
    {
        $sources = new ImageSources($this->assets(['images/logo.avif', 'images/logo.webp', 'images/logo.png', 'images/logo.svg']));

        self::assertSame([
            'src' => '/assets/images/logo-v1.svg',
            'sources' => [['type' => 'image/webp', 'srcset' => '/assets/images/logo-v1.webp']],
        ], $sources->of('images/logo', ['formats' => ['webp'], 'fallbacks' => ['svg']]));
    }

    public function testAnImageWithoutFallbackIsAnError(): void
    {
        try {
            new ImageSources($this->assets(['images/missing.avif']))->of('images/missing');
            self::fail('An image without fallback was read.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('No fallback of "images/missing" among the assets: images/missing.png, images/missing.jpg', $invalidArgumentException->getMessage());
        }

        try {
            new ImageSources($this->assets(['images/missing.png']))->of('images/missing', ['fallbacks' => ['gif']]);
            self::fail('An image without the fallback asked was read.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('No fallback of "images/missing" among the assets: images/missing.gif.', $invalidArgumentException->getMessage());
        }
    }

    public function testAPathWithAnExtensionOrOutsideTheMappedAssetsIsAnError(): void
    {
        $sources = new ImageSources($this->assets(['images/logo.png']));
        $read = [];

        foreach (['images/logo.png', '../images/logo', 'images/../logo', './logo', '', 'images/', "images/logo\n", 'images/.logo'] as $path) {
            try {
                $sources->of($path);
                $read[] = $path;
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertStringContainsString('is not the logical path of an image without its extension', $invalidArgumentException->getMessage(), $path);
            }
        }

        self::assertSame([], $read);
    }

    public function testTheDensitiesCanBeChosen(): void
    {
        $sources = new ImageSources($this->assets(['images/logo.png', 'images/logo@2x.png', 'images/logo@4x.png']));

        self::assertSame('/assets/images/logo-v1.png 1x, /assets/images/logo@4x-v1.png 4x', $sources->of('images/logo', ['densities' => [4]])['srcset'] ?? null);
    }

    public function testWithWidthsAFormatWithoutThemKeepsItsFileWithoutSuffix(): void
    {
        $sources = new ImageSources($this->assets(['images/hall.avif', 'images/hall.jpg']));

        self::assertSame([
            'src' => '/assets/images/hall-v1.jpg',
            'sources' => [['type' => 'image/avif', 'srcset' => '/assets/images/hall-v1.avif']],
            'width' => 96,
            'height' => 64,
        ], $sources->of('images/hall', ['widths' => [480]]));
    }

    public function testAFallbackOfWidthsAloneTakesItsFirstAsItsSource(): void
    {
        $sources = new ImageSources($this->assets(['images/hall@480w.jpg', 'images/hall@960w.jpg']));

        self::assertSame([
            'src' => '/assets/images/hall@480w-v1.jpg',
            'srcset' => '/assets/images/hall@480w-v1.jpg 480w, /assets/images/hall@960w-v1.jpg 960w',
            'sources' => [],
        ], $sources->of('images/hall', ['widths' => [480, 960]]));
    }

    /**
     * Mapped assets served under /assets/, each with the digest "v1"; their files are the fixtures of the same name, which may be missing.
     *
     * @param list<string> $paths
     */
    private function assets(array $paths): AssetMapperInterface
    {
        return new readonly class($paths, self::IMAGES) implements AssetMapperInterface {
            /**
             * @param list<string> $paths
             */
            public function __construct(private array $paths, private string $images)
            {
            }

            #[Override]
            public function getAsset(string $logicalPath): ?MappedAsset
            {
                $file = $this->images.'/'.basename($logicalPath);

                return \in_array($logicalPath, $this->paths, true) ? new MappedAsset($logicalPath, $file, publicPath: $this->getPublicPath($logicalPath)) : null;
            }

            #[Override]
            public function allAssets(): iterable
            {
                return [];
            }

            #[Override]
            public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset
            {
                return null;
            }

            #[Override]
            public function getPublicPath(string $logicalPath): ?string
            {
                return \in_array($logicalPath, $this->paths, true) ? '/assets/'.preg_replace('/\.(\w+)$/', '-v1.$1', $logicalPath) : null;
            }
        };
    }
}
