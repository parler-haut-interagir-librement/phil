<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use Phil\CreationDisclosure\DigitalSourceType;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\Xmp\SourceTypeXmp;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\CreationDisclosure\CreationDisclosureSourceType;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(MediaLibrary::class)]
#[CoversClass(CreationDisclosureSourceType::class)]
final class SourceTypeRenderingTest extends TestCase
{
    private DoctrineMediaRepository $repository;

    private string $directory;

    private MediaLibrary $library;

    protected function setUp(): void
    {
        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $this->directory = Images::directory();
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage($this->directory), new GdImageProcessor(), new NativeClock(), ['thumb' => ImageFormat::of('thumb', 40, 40)], sourceTypes: new CreationDisclosureSourceType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function types(): iterable
    {
        foreach (array_keys(MediaLibrary::TYPES) as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('types')]
    public function testARenderingOfADeclaredMediumCarriesItsSourceTypeOnly(string $type): void
    {
        $media = $this->upload(Images::png());
        $media->disclose(DigitalSourceType::TrainedAlgorithmicMedia);

        $rendered = $this->library->rendered($media, 'thumb', $type);

        self::assertCount(1, Metadata::packets($rendered));
        self::assertSame([SourceTypeXmp::XMP_PROPERTY => Metadata::URI.'trainedAlgorithmicMedia'], Metadata::properties(Metadata::packets($rendered)[0]));
        self::assertSame($rendered, $this->library->rendered($media, 'thumb', $type));
    }

    #[DataProvider('types')]
    public function testARenderingOfAMediumThatDeclaresNothingCarriesNoMetadata(string $type): void
    {
        $media = $this->upload(Images::png());

        $rendered = $this->library->rendered($media, 'thumb', $type);

        self::assertSame([], Metadata::packets($rendered));
        self::assertSame(new GdImageProcessor()->render(Images::png(), ImageFormat::of('thumb', 40, 40), [0.5, 0.5], $type), $rendered);
    }

    public function testAGpsPositionNeverReachesARendering(): void
    {
        $jpeg = Images::jpeg(60, 40);
        $media = $this->upload("\xFF\xD8".Metadata::exifWithGps().substr($jpeg, 2), 'photo.jpg');
        $plain = $this->library->rendered($media, 'thumb', 'jpeg');
        $media->disclose(DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);

        foreach ([$plain, $this->library->rendered($media, 'thumb', 'jpeg'), $this->library->rendered($media, 'original', 'jpeg')] as $rendered) {
            self::assertStringNotContainsString('Exif', $rendered);
            self::assertStringNotContainsString(pack('nnN', 0x8825, 4, 1), $rendered);
        }
    }

    public function testDeclaringOrChangingTheSourceTypeRendersAnew(): void
    {
        $media = $this->upload(Images::png());
        $plain = $this->library->rendered($media, 'thumb', 'webp');
        $media->disclose(DigitalSourceType::AlgorithmicMedia);
        $algorithmic = $this->library->rendered($media, 'thumb', 'webp');
        $media->disclose(DigitalSourceType::TrainedAlgorithmicMedia);
        $generated = $this->library->rendered($media, 'thumb', 'webp');

        self::assertSame([], Metadata::packets($plain));
        self::assertStringContainsString(Metadata::URI.'algorithmicMedia"', $algorithmic);
        self::assertStringContainsString(Metadata::URI.'trainedAlgorithmicMedia"', $generated);
        $files = glob(\sprintf('%s/formats/%s/1/*', $this->directory, $media->id));
        self::assertIsArray($files);
        self::assertCount(3, $files);
        self::assertContains(\sprintf('%s/formats/%s/1/thumb-50-50.webp', $this->directory, $media->id), $files);
        self::assertContains(\sprintf('%s/formats/%s/1/thumb-50-50-%s.webp', $this->directory, $media->id, hash('xxh3', Metadata::URI.'trainedAlgorithmicMedia')), $files);
    }

    public function testWithoutDeclaredSourceTypesNothingIsWritten(): void
    {
        $library = new MediaLibrary($this->repository, new FilesystemStorage($this->directory), new GdImageProcessor(), new NativeClock(), ['thumb' => ImageFormat::of('thumb', 40, 40)]);
        $media = $library->upload($this->file(Images::png()), 'a.png');
        $media->disclose(DigitalSourceType::TrainedAlgorithmicMedia);

        self::assertSame([], Metadata::packets($library->rendered($media, 'thumb', 'png')));
    }

    public function testTheAdapterGivesTheCurrentTypeOfADeclaredCode(): void
    {
        $sourceTypes = new CreationDisclosureSourceType();
        $media = $this->upload(Images::png());
        self::assertNull($sourceTypes->uriOf($media));

        $media->disclose(DigitalSourceType::HumanEdits);
        self::assertSame(Metadata::URI.'humanEdits', $sourceTypes->uriOf($media));

        $code = new ReflectionProperty(Media::class, 'digitalSourceType');
        $code->setValue($media, 'softwareImage');
        self::assertSame(Metadata::URI.'digitalCreation', $sourceTypes->uriOf($media));

        $code->setValue($media, 'madeUp');
        self::assertNull($sourceTypes->uriOf($media));
    }

    private function upload(string $contents, string $filename = 'a.png'): Media
    {
        return $this->library->upload($this->file($contents), $filename);
    }

    private function file(string $contents): string
    {
        $path = $this->directory.'/upload-'.bin2hex(random_bytes(4));
        file_put_contents($path, $contents);

        return $path;
    }
}
