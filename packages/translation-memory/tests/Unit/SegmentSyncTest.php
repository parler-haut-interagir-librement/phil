<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentSync;
use Phil\TranslationMemory\SourceSegment;
use Phil\TranslationMemory\SyncResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentSync::class)]
#[CoversClass(SyncResult::class)]
final class SegmentSyncTest extends TestCase
{
    public function testTheFirstCutMakesUntranslatedSegments(): void
    {
        $result = SegmentSync::sync([], [new SourceSegment('a', 'One'), new SourceSegment('b', 'Two')]);

        self::assertSame(['a' => 'untranslated', 'b' => 'untranslated'], $this->states($result));
        self::assertSame(['a', 'b'], $result->added);
        self::assertSame([], $result->stale);
        self::assertSame([], $result->moved);
        self::assertSame([], $result->removed);
        self::assertTrue($result->hasChanges());
    }

    public function testAnUnchangedSourceKeepsEverySegment(): void
    {
        $stored = [new Segment('a', 'One')->translate('Un'), new Segment('b', 'Two')];
        $result = SegmentSync::sync($stored, [new SourceSegment('a', 'One '), new SourceSegment('b', 'Two')]);

        self::assertSame(['a' => 'translated', 'b' => 'untranslated'], $this->states($result));
        self::assertSame('One ', $result->segments[0]->source);
        self::assertSame('Un', $result->segments[0]->target);
        self::assertFalse($result->hasChanges());
    }

    public function testAChangedSourceMakesItsSegmentStale(): void
    {
        $stored = [new Segment('a', 'One')->translate('Un'), new Segment('b', 'Two')];
        $result = SegmentSync::sync($stored, [new SourceSegment('a', 'One!'), new SourceSegment('b', 'Two!')]);

        self::assertSame(['a' => 'stale', 'b' => 'untranslated'], $this->states($result));
        self::assertSame(['a'], $result->stale);
        self::assertSame([], $result->added);
        self::assertSame('Two!', $result->segments[1]->source);
        self::assertTrue($result->hasChanges());
    }

    public function testASentenceInsertedBeforeOthersMovesThemWithTheirTranslations(): void
    {
        $stored = [new Segment('b1/text#1', 'One.')->translate('Un.'), new Segment('b1/text#2', 'Two.')->translate('Deux.')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b1/text#1', 'Zero.'), new SourceSegment('b1/text#2', 'One.'), new SourceSegment('b1/text#3', 'Two.')]);

        self::assertSame(['b1/text#1' => 'untranslated', 'b1/text#2' => 'translated', 'b1/text#3' => 'translated'], $this->states($result));
        self::assertSame(['Un.', 'Deux.'], [$result->segments[1]->target, $result->segments[2]->target]);
        self::assertSame(['b1/text#2' => 'b1/text#1', 'b1/text#3' => 'b1/text#2'], $result->moved);
        self::assertSame(['b1/text#1'], $result->added);
        self::assertSame([], $result->stale);
        self::assertSame([], $result->removed);
    }

    public function testEveryChangedSourceIsListedAsStale(): void
    {
        $stored = [new Segment('a', 'One')->translate('Un'), new Segment('b', 'Two')->translate('Deux')];
        $result = SegmentSync::sync($stored, [new SourceSegment('a', 'One!'), new SourceSegment('b', 'Two!')]);

        self::assertSame(['a', 'b'], $result->stale);
    }

    public function testAMoveFromAnotherFieldTakesTheFirstFound(): void
    {
        $stored = [new Segment('b1/text', 'Hello')->translate('Bonjour'), new Segment('b2/text', 'Hello')->translate('Salut')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b9/text', 'Hello')]);

        self::assertSame(['b9/text' => 'b1/text'], $result->moved);
    }

    public function testTheFieldOfAKeyIsAllBeforeTheSentenceNumber(): void
    {
        $stored = [new Segment('x#1', 'Yes')->translate('Oui'), new Segment('y#1', 'Yes')->translate('Si')];
        $result = SegmentSync::sync($stored, [new SourceSegment('y#2', 'Yes')]);

        self::assertSame(['y#2' => 'y#1'], $result->moved);
    }

    public function testAMoveIsTakenFromTheSameFieldFirst(): void
    {
        $stored = [new Segment('b1/text#1', 'Yes.')->translate('Oui.'), new Segment('b2/text#1', 'Yes.')->translate('Si.'), new Segment('b2/text#2', 'No.')->translate('Non.')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b2/text#1', 'No.'), new SourceSegment('b2/text#2', 'Yes.')]);

        self::assertSame(['b2/text#1' => 'b2/text#2', 'b2/text#2' => 'b2/text#1'], $result->moved);
        self::assertSame(['Non.', 'Si.'], [$result->segments[0]->target, $result->segments[1]->target]);
        self::assertSame(['b1/text#1'], array_map(static fn (Segment $segment): string => $segment->key, $result->removed));
    }

    public function testAMoveToAnotherFieldIsTakenWhenTheFieldHasNone(): void
    {
        $stored = [new Segment('b1/text', 'Hello')->translate('Bonjour'), new Segment('b2/text', 'Other')->translate('Autre')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b9/text', 'Hello')]);

        self::assertSame(['b9/text' => 'b1/text'], $result->moved);
        self::assertSame('Bonjour', $result->segments[0]->target);
        self::assertSame(['b2/text'], array_map(static fn (Segment $segment): string => $segment->key, $result->removed));
    }

    public function testAMoveNeedsATarget(): void
    {
        $stored = [new Segment('b1/text', 'Hello')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b9/text', 'Hello')]);

        self::assertSame([], $result->moved);
        self::assertSame(['b9/text'], $result->added);
        self::assertCount(1, $result->removed);
    }

    public function testAStoredSegmentMovedAwayIsNotAlsoMadeStale(): void
    {
        $stored = [new Segment('b1/text', 'Hello')->translate('Bonjour')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b1/text', 'Hi'), new SourceSegment('b2/text', 'Hello')]);

        self::assertSame(['b1/text' => 'untranslated', 'b2/text' => 'translated'], $this->states($result));
        self::assertSame(['b2/text' => 'b1/text'], $result->moved);
        self::assertSame(['b1/text'], $result->added);
        self::assertSame([], $result->stale);
        self::assertSame([], $result->removed);
    }

    public function testAKeyKeptWithItsTextIsNotTakenByAMove(): void
    {
        $stored = [new Segment('a', 'Hello')->translate('Bonjour'), new Segment('b', 'Hello')->translate('Salut')];
        $result = SegmentSync::sync($stored, [new SourceSegment('b', 'Hello'), new SourceSegment('c', 'Hello')]);

        self::assertSame(['Salut', 'Bonjour'], [$result->segments[0]->target, $result->segments[1]->target]);
        self::assertSame(['c' => 'a'], $result->moved);
    }

    public function testASegmentNoLongerInTheSourceIsRemoved(): void
    {
        $stored = [new Segment('a', 'One')->translate('Un'), new Segment('b', 'Two')];
        $result = SegmentSync::sync($stored, [new SourceSegment('a', 'One')]);

        self::assertSame(['a' => 'translated'], $this->states($result));
        self::assertSame(['b'], array_map(static fn (Segment $segment): string => $segment->key, $result->removed));
        self::assertTrue($result->hasChanges());
        self::assertTrue(new SyncResult([], moved: ['a' => 'b'])->hasChanges());
        self::assertTrue(new SyncResult([], stale: ['a'])->hasChanges());
    }

    /**
     * @return array<string, string>
     */
    private function states(SyncResult $result): array
    {
        $states = [];

        foreach ($result->segments as $segment) {
            $states[$segment->key] = $segment->state->value;
        }

        return $states;
    }
}
