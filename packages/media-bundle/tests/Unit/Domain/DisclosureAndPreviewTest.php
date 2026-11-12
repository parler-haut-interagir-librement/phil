<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\MediaBundle\Application\MediaSchema;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Media\AiDisclosure;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Media::class)]
#[CoversClass(MediaSchema::class)]
final class DisclosureAndPreviewTest extends TestCase
{
    /**
     * @return iterable<string, array{DigitalSourceType, AiDisclosure, string}>
     */
    public static function sources(): iterable
    {
        yield 'created with generative AI' => [DigitalSourceType::TrainedAlgorithmicMedia, AiDisclosure::Generated, 'trainedAlgorithmicMedia'];
        yield 'edited with generative AI' => [DigitalSourceType::CompositeWithTrainedAlgorithmicMedia, AiDisclosure::Assisted, 'compositeWithTrainedAlgorithmicMedia'];
        yield 'possibly generated' => [DigitalSourceType::AlgorithmicallyEnhanced, AiDisclosure::Assisted, 'algorithmicallyEnhanced'];
        yield 'photographed' => [DigitalSourceType::DigitalCapture, AiDisclosure::None, 'digitalCapture'];
        yield 'retired' => [DigitalSourceType::DigitalArt, AiDisclosure::None, 'digitalCreation'];
    }

    #[DataProvider('sources')]
    public function testADeclaredSourceSetsTheAiDisclosure(DigitalSourceType $type, AiDisclosure $disclosure, string $code): void
    {
        $media = $this->media();
        $media->license('CC-BY-4.0', AiDisclosure::Generated);
        $media->disclose($type);

        self::assertSame([$disclosure, $code, 'CC-BY-4.0'], [$media->aiDisclosure(), $media->digitalSourceType(), $media->licenseId()]);
        self::assertSame('https://cv.iptc.org/newscodes/digitalsourcetype/'.$code, MediaSchema::of($media, 'https://example.com/a.png')['digitalSourceType']);
    }

    public function testALicenseWithAnAiDisclosureForgetsTheSource(): void
    {
        $media = $this->media();
        self::assertNull($media->digitalSourceType());
        $media->disclose(DigitalSourceType::HumanEdits);
        $media->license('CC0-1.0', AiDisclosure::Assisted);

        self::assertNull($media->digitalSourceType());
        self::assertSame('https://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia', MediaSchema::of($media, 'https://example.com/a.png')['digitalSourceType']);
    }

    public function testAPlaceholderIsKeptUntilTheNextVersion(): void
    {
        $media = $this->media();
        self::assertNull($media->placeholder());
        $media->preview('data:image/webp;base64,UklGRg==');
        self::assertSame('data:image/webp;base64,UklGRg==', $media->placeholder());
        $media->preview('data:image/png;base64,'.str_repeat('A', 4096 - 22));
        self::assertSame(4096, \strlen((string) $media->placeholder()));

        $media->replace(FileInfo::of('b.png', 'image/png', 10, 4, 4), new DateTimeImmutable('2026-11-05 UTC'));
        self::assertNull($media->placeholder());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPlaceholders(): iterable
    {
        yield 'too long' => ['data:image/png;base64,'.str_repeat('A', 4096 - 21)];
        yield 'another scheme' => ['https://example.com/a.webp'];
        yield 'not an image' => ['data:text/html;base64,PGI+'];
        yield 'a vector image' => ['data:image/svg+xml;base64,PHN2Zz4='];
        yield 'not base64' => ['data:image/webp;base64,"><script>'];
        yield 'empty data' => ['data:image/webp;base64,'];
        yield 'prefixed' => [' data:image/webp;base64,UklGRg=='];
        yield 'suffixed' => ["data:image/webp;base64,UklGRg==\n"];
        yield 'too much padding' => ['data:image/webp;base64,UklGRg==='];
        yield 'a jpeg written jpg' => ['data:image/jpg;base64,UklGRg=='];
    }

    #[DataProvider('invalidPlaceholders')]
    public function testAPlaceholderIsADataUrlOfARasterImage(string $placeholder): void
    {
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('A placeholder is a data: URL of a WebP, AVIF, JPEG or PNG image in base64, 4096 characters or fewer.');

        $this->media()->preview($placeholder);
    }

    public function testEveryRasterTypeIsAPlaceholder(): void
    {
        $media = $this->media();

        foreach (['webp', 'avif', 'jpeg', 'png'] as $type) {
            $media->preview('data:image/'.$type.';base64,QQ=');
            self::assertSame('data:image/'.$type.';base64,QQ=', $media->placeholder());
        }
    }

    private function media(): Media
    {
        return Media::upload(FileInfo::of('a.png', 'image/png', 10, 4, 4), null, new DateTimeImmutable('2026-11-04 UTC'));
    }
}
