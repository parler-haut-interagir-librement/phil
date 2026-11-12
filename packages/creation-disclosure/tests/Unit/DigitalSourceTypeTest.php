<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure\Tests\Unit;

use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\Exception\CreationDisclosureException;
use Phil\CreationDisclosure\Exception\InvalidDisclosure;
use Phil\CreationDisclosure\GenerativeAi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DigitalSourceType::class)]
#[CoversClass(InvalidDisclosure::class)]
final class DigitalSourceTypeTest extends TestCase
{
    public function testTheVocabularyIsComplete(): void
    {
        self::assertCount(20, DigitalSourceType::cases());
        self::assertCount(17, DigitalSourceType::declarable());
        self::assertSame(DigitalSourceType::DigitalCapture, DigitalSourceType::declarable()[0]);
        self::assertSame(DigitalSourceType::CompositeSynthetic, DigitalSourceType::declarable()[16]);
        self::assertSame('Iptc4xmpExt:DigitalSourceType', DigitalSourceType::XMP_PROPERTY);
    }

    public function testACodeIsRead(): void
    {
        self::assertSame(DigitalSourceType::TrainedAlgorithmicMedia, DigitalSourceType::fromCode('trainedAlgorithmicMedia'));
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(CreationDisclosureException::class);
        $this->expectExceptionMessageIsOrContains('"TrainedAlgorithmicMedia" is not a code of the IPTC Digital Source Type vocabulary.');

        DigitalSourceType::fromCode('TrainedAlgorithmicMedia');
    }

    public function testALongValueIsShortenedInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 80).'…" is not a code');

        DigitalSourceType::fromCode(str_repeat('é', 81));
    }

    public function testAValueIsShortenedFromItsStart(): void
    {
        $this->expectExceptionMessageIsOrContains('"x'.str_repeat('a', 79).'…" is not a code');

        DigitalSourceType::fromCode('x'.str_repeat('a', 90));
    }

    public function testAValueIsMeasuredInCharactersNotBytes(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 80).'" is not a code');

        DigitalSourceType::fromCode(str_repeat('é', 80));
    }

    public function testAValueOfEightyCharactersIsShownWhole(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('a', 80).'" is not a code');

        DigitalSourceType::fromCode(str_repeat('a', 80));
    }

    public function testTheAddressIsThatOfTheVocabulary(): void
    {
        self::assertSame('http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture', DigitalSourceType::DigitalCapture->uri());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addresses(): iterable
    {
        yield 'http' => ['http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'https' => ['https://cv.iptc.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'trailing slash' => ['http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits/'];
        yield 'spaces' => ["  http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits\n"];
    }

    #[DataProvider('addresses')]
    public function testAnAddressIsRead(string $uri): void
    {
        self::assertSame(DigitalSourceType::HumanEdits, DigitalSourceType::fromUri($uri));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignAddresses(): iterable
    {
        yield 'unknown code' => ['http://cv.iptc.org/newscodes/digitalsourcetype/handMade'];
        yield 'another host' => ['http://cv.example.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'any character for the dot' => ['http://cvXiptc.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'another scheme' => ['ftp://cv.iptc.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'prefixed' => ['xhttp://cv.iptc.org/newscodes/digitalsourcetype/humanEdits'];
        yield 'suffixed' => ['http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits#x'];
        yield 'two slashes' => ['http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits//'];
        yield 'a code alone' => ['humanEdits'];
        yield 'no code' => ['http://cv.iptc.org/newscodes/digitalsourcetype/'];
        yield 'a code too long' => ['http://cv.iptc.org/newscodes/digitalsourcetype/'.str_repeat('a', 65)];
        yield 'a final newline' => ["http://cv.iptc.org/newscodes/digitalsourcetype/humanEdits/\n/"];
    }

    #[DataProvider('foreignAddresses')]
    public function testAForeignAddressIsRefused(string $uri): void
    {
        $this->expectException(InvalidDisclosure::class);
        $this->expectExceptionMessageIsOrContains('is not an address of the IPTC Digital Source Type vocabulary.');

        DigitalSourceType::fromUri($uri);
    }

    public function testRetiredTypesPointToTheirReplacement(): void
    {
        $retired = array_values(array_filter(DigitalSourceType::cases(), static fn (DigitalSourceType $type): bool => $type->isRetired()));

        self::assertSame([DigitalSourceType::MinorHumanEdits, DigitalSourceType::SoftwareImage, DigitalSourceType::DigitalArt], $retired);
        self::assertSame(DigitalSourceType::HumanEdits, DigitalSourceType::MinorHumanEdits->current());
        self::assertSame(DigitalSourceType::DigitalCreation, DigitalSourceType::SoftwareImage->current());
        self::assertSame(DigitalSourceType::DigitalCreation, DigitalSourceType::DigitalArt->current());
        self::assertSame(DigitalSourceType::Print, DigitalSourceType::Print->current());
    }

    /**
     * @return iterable<string, array{DigitalSourceType, GenerativeAi}>
     */
    public static function views(): iterable
    {
        foreach (DigitalSourceType::cases() as $type) {
            yield $type->value => [$type, match ($type) {
                DigitalSourceType::TrainedAlgorithmicMedia => GenerativeAi::Full,
                DigitalSourceType::CompositeWithTrainedAlgorithmicMedia, DigitalSourceType::CompositeSynthetic => GenerativeAi::Partial,
                DigitalSourceType::AlgorithmicallyEnhanced, DigitalSourceType::Composite => GenerativeAi::Possible,
                default => GenerativeAi::None,
            }];
        }
    }

    #[DataProvider('views')]
    public function testEachTypeSaysWhatItOwesToGenerativeAi(DigitalSourceType $type, GenerativeAi $view): void
    {
        self::assertSame($view, $type->generativeAi());
    }

    public function testEachTypeHasItsOwnLabel(): void
    {
        $labels = array_map(static fn (DigitalSourceType $type): string => $type->label(), DigitalSourceType::cases());

        self::assertCount(20, array_unique($labels));
        self::assertSame('Created using Generative AI', DigitalSourceType::TrainedAlgorithmicMedia->label());
        self::assertSame('Edited using Generative AI', DigitalSourceType::CompositeWithTrainedAlgorithmicMedia->label());
        self::assertSame([
            'Digital capture sampled from real life',
            'Multi-frame computational capture sampled from real life',
            'Digitised from a transparent negative',
            'Digitised from a transparent positive',
            'Digitised from a non-transparent medium',
            'Human-edited media',
            'Edited using Generative AI',
            'Algorithmically-altered media',
            'Digital creation',
            'Data-driven media',
            'Created using Generative AI',
            'Pure algorithmic media',
            'Screen capture',
            'Virtual event recording',
            'Composite of elements',
            'Composite of captured elements',
            'Composite including generative AI elements',
            'Original media with minor human edits',
            'Created by software',
            'Digital art',
        ], $labels);
    }
}
