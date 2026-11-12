<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\SourceSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Segment::class)]
#[CoversClass(SegmentState::class)]
#[CoversClass(SourceSegment::class)]
#[CoversClass(InvalidSegment::class)]
final class SegmentTest extends TestCase
{
    public function testANewSegmentIsUntranslated(): void
    {
        $segment = Segment::fromSource(new SourceSegment('b1/text', 'Hello world'));

        self::assertSame('b1/text', $segment->key);
        self::assertSame('Hello world', $segment->source);
        self::assertTrue(Fingerprint::of('Hello world')->equals($segment->fingerprint));
        self::assertSame(SegmentState::Untranslated, $segment->state);
        self::assertNull($segment->target);
        self::assertNull($segment->translatedSource);
        self::assertFalse($segment->isUsable());
    }

    public function testATranslationIsWrittenForTheCurrentSource(): void
    {
        $segment = new Segment('k', 'Hello')->translate('Bonjour');

        self::assertSame(SegmentState::Translated, $segment->state);
        self::assertSame('Bonjour', $segment->target);
        self::assertSame('Hello', $segment->translatedSource);
        self::assertTrue($segment->isUsable());
    }

    public function testASuggestionIsNotUsable(): void
    {
        $segment = new Segment('k', 'Hello')->suggest('Salut');

        self::assertSame(SegmentState::Suggested, $segment->state);
        self::assertSame('Salut', $segment->target);
        self::assertSame('Hello', $segment->translatedSource);
        self::assertFalse($segment->isUsable());
    }

    public function testATranslatedOrSuggestedTargetIsReviewed(): void
    {
        foreach ([new Segment('k', 'Hello')->translate('Bonjour'), new Segment('k', 'Hello')->suggest('Bonjour')] as $segment) {
            $reviewed = $segment->review();

            self::assertSame(SegmentState::Reviewed, $reviewed->state);
            self::assertSame('Bonjour', $reviewed->target);
            self::assertSame('Hello', $reviewed->translatedSource);
            self::assertTrue($reviewed->isUsable());
            self::assertSame(SegmentState::Reviewed, $reviewed->review()->state);
        }
    }

    public function testAnUntranslatedSegmentIsNotReviewed(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('Segment "k" is untranslated: only a target written for its source can be reviewed.');

        new Segment('k', 'Hello')->review();
    }

    public function testAStaleSegmentIsNotReviewed(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('Segment "k" is stale: only a target written for its source can be reviewed.');

        new Segment('k', 'Hello')->translate('Bonjour')->withSource('Hello you')->review();
    }

    public function testTheSameSourceKeepsTheSegment(): void
    {
        $segment = new Segment('k', 'Hello  world')->translate('Bonjour le monde')->review()->withSource(' Hello world ');

        self::assertSame(' Hello world ', $segment->source);
        self::assertSame(SegmentState::Reviewed, $segment->state);
        self::assertSame('Bonjour le monde', $segment->target);
        self::assertSame('Hello  world', $segment->translatedSource);
    }

    public function testAChangedSourceMakesATargetStale(): void
    {
        $segment = new Segment('k', 'Hello')->translate('Bonjour')->withSource('Hello you');

        self::assertSame('Hello you', $segment->source);
        self::assertSame(SegmentState::Stale, $segment->state);
        self::assertSame('Bonjour', $segment->target);
        self::assertSame('Hello', $segment->translatedSource);
        self::assertFalse($segment->isUsable());

        $again = $segment->withSource('Hello all');

        self::assertSame(SegmentState::Stale, $again->state);
        self::assertSame('Hello', $again->translatedSource);
    }

    public function testAStaleSegmentWithoutItsSourceKeepsTheSourceItHad(): void
    {
        $segment = new Segment('k', 'Hello', SegmentState::Translated, 'Bonjour')->withSource('Hi');

        self::assertSame(SegmentState::Stale, $segment->state);
        self::assertSame('Hello', $segment->translatedSource);
    }

    public function testASourceBackToItsTranslatedTextIsTranslatedAgain(): void
    {
        $segment = new Segment('k', 'Hello')->translate('Bonjour')->review()->withSource('Hello you')->withSource('Hello ');

        self::assertSame(SegmentState::Translated, $segment->state);
        self::assertSame('Hello ', $segment->source);
        self::assertSame('Hello ', $segment->translatedSource);
    }

    public function testAChangedSourceLeavesAnUntranslatedSegmentUntranslated(): void
    {
        $segment = new Segment('k', 'Hello')->withSource('Hello you');

        self::assertSame(SegmentState::Untranslated, $segment->state);
        self::assertSame('Hello you', $segment->source);
        self::assertNull($segment->translatedSource);
    }

    public function testAStaleTargetThatStillHoldsIsConfirmed(): void
    {
        $segment = new Segment('k', 'Hello!')->translate('Bonjour !')->withSource('Hello.')->confirm();

        self::assertSame(SegmentState::Translated, $segment->state);
        self::assertSame('Bonjour !', $segment->target);
        self::assertSame('Hello.', $segment->translatedSource);
    }

    public function testOnlyAStaleSegmentIsConfirmed(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('Segment "k" is translated: only a stale segment is confirmed.');

        new Segment('k', 'Hello')->translate('Bonjour')->confirm();
    }

    public function testAClearedSegmentIsUntranslated(): void
    {
        $segment = new Segment('k', 'Hello')->translate('Bonjour')->clear();

        self::assertSame(SegmentState::Untranslated, $segment->state);
        self::assertNull($segment->target);
        self::assertNull($segment->translatedSource);
        self::assertSame('Hello', $segment->source);
    }

    public function testASegmentMovesToAnotherKey(): void
    {
        $segment = new Segment('a', 'Hello')->translate('Bonjour')->withKey('b');

        self::assertSame('b', $segment->key);
        self::assertSame(SegmentState::Translated, $segment->state);
        self::assertSame('Bonjour', $segment->target);
        self::assertSame('Hello', $segment->translatedSource);
    }

    public function testAKeyOfTheLongestLengthIsAccepted(): void
    {
        self::assertSame(255, \strlen(new Segment(str_repeat('k', 255), 'x')->key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('k', 256)];
        yield 'not UTF-8' => ["\xFF"];
    }

    #[DataProvider('invalidKeys')]
    public function testRefusesAKey(string $key): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('A segment key is 1 to 255 bytes of UTF-8.');

        new Segment($key, 'x');
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'source' => ["\xFF", null, null];
        yield 'target' => ['x', "\xFF", null];
        yield 'translated source' => ['x', 'y', "\xFF"];
    }

    #[DataProvider('invalidTexts')]
    public function testRefusesATextThatIsNotUtf8(string $source, ?string $target, ?string $translatedSource): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('The texts of segment "k" must be valid UTF-8.');

        new Segment('k', $source, SegmentState::Translated, $target ?? 'y', $translatedSource);
    }

    public function testRefusesATargetWithoutState(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('Segment "k" is untranslated: it must not have a target.');

        new Segment('k', 'x', SegmentState::Untranslated, 'y');
    }

    public function testRefusesAStateWithoutTarget(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('Segment "k" is reviewed: it must have a target.');

        new Segment('k', 'x', SegmentState::Reviewed);
    }

    public function testEachStateSaysWhetherItIsUsableAndHoldsATarget(): void
    {
        self::assertSame(
            ['untranslated' => [false, false], 'suggested' => [false, true], 'translated' => [true, true], 'reviewed' => [true, true], 'stale' => [false, true]],
            array_combine(
                array_map(static fn (SegmentState $state): string => $state->value, SegmentState::cases()),
                array_map(static fn (SegmentState $state): array => [$state->isUsable(), $state->hasTarget()], SegmentState::cases()),
            ),
        );
    }
}
