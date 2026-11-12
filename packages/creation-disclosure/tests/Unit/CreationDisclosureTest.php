<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure\Tests\Unit;

use Phil\CreationDisclosure\CreationDisclosure;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\Exception\InvalidDisclosure;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreationDisclosure::class)]
#[CoversClass(InvalidDisclosure::class)]
final class CreationDisclosureTest extends TestCase
{
    public function testAWorkStartsUndeclared(): void
    {
        $work = CreationDisclosure::of(Part::Image, Part::Text, Part::Image);

        self::assertSame([Part::Text, Part::Image], $work->parts());
        self::assertSame([Part::Text, Part::Image], $work->undeclared());
        self::assertSame(['text' => null, 'image' => null], $work->toArray());
        self::assertSame(GenerativeAi::Undeclared, $work->generativeAi());
        self::assertFalse($work->isFreeOfGenerativeAi());
        self::assertTrue($work->has(Part::Text));
        self::assertFalse($work->has(Part::Code));
    }

    public function testADeclarationReturnsANewDisclosure(): void
    {
        $work = CreationDisclosure::of(Part::Text, Part::Image);
        $declared = $work->declare(Part::Image, DigitalSourceType::TrainedAlgorithmicMedia)->declare(Part::Text, DigitalSourceType::HumanEdits);

        self::assertSame([Part::Text, Part::Image], $work->undeclared());
        self::assertSame([], $declared->undeclared());
        self::assertSame(DigitalSourceType::HumanEdits, $declared->sourceOf(Part::Text));
        self::assertSame(GenerativeAi::None, $declared->generativeAiOf(Part::Text));
        self::assertSame(GenerativeAi::Full, $declared->generativeAiOf(Part::Image));
        self::assertSame(GenerativeAi::Partial, $declared->generativeAi());
        self::assertSame(['text' => 'humanEdits', 'image' => 'trainedAlgorithmicMedia'], $declared->toArray());
    }

    public function testAnAiFreeWorkDeclaresEveryPart(): void
    {
        $work = CreationDisclosure::of(Part::Text)->declare(Part::Text, DigitalSourceType::HumanEdits);

        self::assertTrue($work->isFreeOfGenerativeAi());
        self::assertFalse($work->declare(Part::Audio, DigitalSourceType::DigitalCapture)->undeclare(Part::Code)->isFreeOfGenerativeAi());
        self::assertTrue($work->declare(Part::Audio, DigitalSourceType::DigitalCapture)->isFreeOfGenerativeAi());
    }

    public function testDeclaringAddsAPartInItsPlace(): void
    {
        $work = CreationDisclosure::of(Part::Code)->declare(Part::Text, DigitalSourceType::DigitalCreation);

        self::assertSame([Part::Text, Part::Code], $work->parts());
        self::assertSame([Part::Code], $work->undeclared());
    }

    public function testAPartIsUndeclaredAgain(): void
    {
        $work = CreationDisclosure::of()->declare(Part::Video, DigitalSourceType::DigitalCapture)->undeclare(Part::Video)->undeclare(Part::Audio);

        self::assertSame([Part::Audio, Part::Video], $work->parts());
        self::assertNull($work->sourceOf(Part::Video));
        self::assertSame(GenerativeAi::Undeclared, $work->generativeAiOf(Part::Video));
        self::assertSame(GenerativeAi::Undeclared, $work->generativeAiOf(Part::Text));
    }

    public function testARetiredTypeIsDeclaredAsItsReplacement(): void
    {
        self::assertSame(DigitalSourceType::DigitalCreation, CreationDisclosure::of()->declare(Part::Image, DigitalSourceType::DigitalArt)->sourceOf(Part::Image));
    }

    public function testAStoredDisclosureIsReadBack(): void
    {
        $work = CreationDisclosure::fromArray([
            'image' => 'http://cv.iptc.org/newscodes/digitalsourcetype/compositeSynthetic',
            'code' => null,
            'text' => 'minorHumanEdits',
        ]);

        self::assertSame([Part::Text, Part::Image, Part::Code], $work->parts());
        self::assertSame(DigitalSourceType::CompositeSynthetic, $work->sourceOf(Part::Image));
        self::assertSame(DigitalSourceType::MinorHumanEdits, $work->sourceOf(Part::Text));
        self::assertSame($work->toArray(), CreationDisclosure::fromArray($work->toArray())->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidStores(): iterable
    {
        yield 'unknown part' => [['music' => null], '"music" is not a part of a work: text, image, audio, video, code.'];
        yield 'numeric key' => [[0 => null], '"0" is not a part of a work'];
        yield 'not a string' => [['text' => 3], 'The source of the part "text" is a code of the vocabulary, or null when undeclared.'];
        yield 'unknown code' => [['text' => 'handMade'], '"handMade" is not a code'];
        yield 'unknown address' => [['text' => 'https://example.org/handMade'], '"https://example.org/handMade" is not an address'];
    }

    /**
     * @param array<array-key, mixed> $stored
     */
    #[DataProvider('invalidStores')]
    public function testAnInvalidStoreIsRefused(array $stored, string $message): void
    {
        $this->expectException(InvalidDisclosure::class);
        $this->expectExceptionMessageIsOrContains($message);

        CreationDisclosure::fromArray($stored);
    }
}
