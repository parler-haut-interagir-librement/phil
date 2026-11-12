<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\MediaBundle\Application\Contract\SvgPurifier;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaLibrary::class)]
final class PlaceholderAndSvgTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    private FilesystemStorage $storage;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineMediaRepository($this->entityManager);
        $this->storage = new FilesystemStorage(Images::directory());
    }

    public function testEachVersionOfAnImageGetsItsPlaceholder(): void
    {
        $library = $this->library(new GdImageProcessor());
        $media = $library->upload(Images::file(Images::png(400, 200)), 'a.png');

        $placeholder = (string) $media->placeholder();
        self::assertStringStartsWith('data:image/webp;base64,', $placeholder);
        $size = getimagesizefromstring((string) base64_decode(substr($placeholder, 23), true));
        self::assertIsArray($size);
        self::assertSame([16, 8, 'image/webp'], [$size[0], $size[1], $size['mime']]);
        $this->entityManager->clear();
        $media = $this->repository->get($media->id);
        self::assertInstanceOf(Media::class, $media);
        self::assertSame($placeholder, $media->placeholder());
        self::assertSame($placeholder, $library->placeholder($media));

        $library->replace($media, Images::file(Images::jpeg(100, 400)), 'b.jpg');
        $size = getimagesizefromstring((string) base64_decode(substr((string) $media->placeholder(), 23), true));
        self::assertIsArray($size);
        self::assertSame([4, 16], [$size[0], $size[1]]);
    }

    public function testAPlaceholderIsRenderedOnce(): void
    {
        $processor = $this->processor('RIFF');
        $library = $this->library($processor);
        $media = $library->upload(Images::file(Images::png(40, 20)), 'a.png');

        self::assertSame('data:image/webp;base64,UklGRg==', $library->placeholder($media));
        self::assertSame(1, $processor->calls);
        self::assertSame([['placeholder', 'webp', [0.5, 0.5]]], $processor->renders);
    }

    public function testWithoutAFormatOrARasterImageThereIsNoPlaceholder(): void
    {
        $processor = $this->processor('RIFF');
        $none = new MediaLibrary($this->repository, $this->storage, $processor, $this->clock());
        self::assertNull($none->upload(Images::file(Images::png(40, 20)), 'a.png')->placeholder());

        $library = $this->library($processor);
        self::assertNull($library->upload(Images::file('%PDF-1.4'), 'a.pdf')->placeholder());
        self::assertNull($library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'a.svg')->placeholder());
        self::assertNull($library->upload(Images::file("\x89PNG\r\n\x1a\n broken"), 'broken.png')->placeholder());
        ob_start();
        imagebmp(Images::canvas(4, 4));
        $bitmap = $library->upload(Images::file((string) ob_get_clean()), 'a.bmp');
        self::assertSame(4, $bitmap->file()->width);
        self::assertNull($bitmap->placeholder());
        self::assertSame(0, $processor->calls);
    }

    public function testAnImageThatCannotBeRenderedOrIsTooLargeHasNoPlaceholder(): void
    {
        $failing = new class implements ImageProcessor {
            public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
            {
                throw InvalidMedia::because('No.');
            }
        };
        self::assertNull($this->library($failing)->upload(Images::file(Images::png(40, 20)), 'a.png')->placeholder());

        // 3,054 bytes encode to 4,072 characters, 4,095 with the prefix: kept; three more bytes go past 4,096.
        self::assertSame(4095, \strlen((string) $this->library($this->processor(str_repeat('a', 3054)))->upload(Images::file(Images::png(40, 20)), 'a.png')->placeholder()));
        self::assertNull($this->library($this->processor(str_repeat('a', 3057)))->upload(Images::file(Images::png(40, 20)), 'a.png')->placeholder());
    }

    public function testAnSvgImageIsPurifiedBeforeItIsKept(): void
    {
        $library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock(), svg: new SanitizingSvgPurifier());
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 10 10\">\n  <!-- a comment -->\n  <script>alert(1)</script>\n  <path d=\"M0.123456 1.98765L5 5\"/>\n</svg>\n";
        $media = $library->upload(Images::file($svg), 'logo.svg');
        $kept = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0.123 1.988L5 5"/></svg>';

        self::assertSame($kept, $library->original($media));
        self::assertSame(['image/svg+xml', \strlen($kept), null, null], [$media->file()->mimeType, $media->file()->size, $media->file()->width, $media->file()->height]);

        $library->replace($media, Images::file('<svg xmlns="http://www.w3.org/2000/svg"><circle r="1" onclick="x()"/></svg>'), 'logo.svg');
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>', $library->original($media));

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The SVG image is refused: entity_declaration.');
        $library->upload(Images::file('<!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg"/>'), 'bomb.svg');
    }

    public function testOnlyAnSvgImageIsPurified(): void
    {
        $purifier = new class implements SvgPurifier {
            public int $calls = 0;

            public function purify(string $svg): string
            {
                ++$this->calls;

                return $svg;
            }
        };
        $library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock(), svg: $purifier);
        $media = $library->upload(Images::file(Images::png(40, 20)), 'a.png');

        self::assertSame(0, $purifier->calls);
        self::assertSame([40, 20], [$media->file()->width, $media->file()->height]);
        self::assertSame(Images::png(40, 20), $library->original($media));
        self::assertNull($library->purifiedSvg($media));
        self::assertSame(0, $purifier->calls);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $plain = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock());
        $kept = $plain->upload(Images::file($svg), 'a.svg');
        self::assertSame($svg, $plain->original($kept));
        self::assertNull($plain->purifiedSvg($kept));
        self::assertSame($svg, $library->purifiedSvg($kept));
        self::assertSame(1, $purifier->calls);
        // Kept beside the formats of its version, then read back.
        self::assertSame($svg, $this->storage->read(\sprintf('formats/%s/1/purified.svg', $kept->id)));
        $this->storage->write(\sprintf('formats/%s/1/purified.svg', $kept->id), '<svg/>');
        self::assertSame('<svg/>', $library->purifiedSvg($kept));
        self::assertSame(1, $purifier->calls);
    }

    public function testTheLibrarySaysWhetherItKeepsPreviews(): void
    {
        self::assertTrue($this->library(new GdImageProcessor())->hasPlaceholders());
        self::assertFalse(new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock())->hasPlaceholders());
    }

    private function library(ImageProcessor $processor): MediaLibrary
    {
        return new MediaLibrary($this->repository, $this->storage, $processor, $this->clock(), placeholder: ImageFormat::of('placeholder', 16, 16, 'fit', 40));
    }

    /**
     * @return ImageProcessor&object{calls: int, renders: list<array{string, string, array{float, float}}>}
     */
    private function processor(string $output): ImageProcessor
    {
        return new class($output) implements ImageProcessor {
            public int $calls = 0;

            /** @var list<array{string, string, array{float, float}}> */
            public array $renders = [];

            public function __construct(private readonly string $output)
            {
            }

            public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
            {
                ++$this->calls;
                $this->renders[] = [$format->name, $type, $focalPoint];

                return $this->output;
            }
        };
    }

    private function clock(): MutableClock
    {
        return new MutableClock(new DateTimeImmutable('2026-11-04 12:00 UTC'));
    }
}
