<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Xliff;

use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Xliff\MergeResult;
use Phil\TranslationMemory\Xliff\Skipped;
use Phil\TranslationMemory\Xliff\SkipReason;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffMerge;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use Phil\TranslationMemory\Xliff\XliffWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XliffMerge::class)]
#[CoversClass(MergeResult::class)]
#[CoversClass(Skipped::class)]
#[CoversClass(SkipReason::class)]
final class XliffMergeTest extends TestCase
{
    public function testAppliesEachTargetInTheStateOfTheFile(): void
    {
        $segments = [new Segment('a', 'One'), new Segment('b', 'Two'), new Segment('c', 'Three'), new Segment('d', 'Four')];
        $result = XliffMerge::merge($segments, $this->file([
            new XliffUnit('a', 'One', 'Un', SegmentState::Translated),
            new XliffUnit('b', 'Two', 'Deux', SegmentState::Reviewed),
            new XliffUnit('c', 'Three', 'Trois', SegmentState::Suggested),
        ]), LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertSame([['a', 'translated', 'Un'], ['b', 'reviewed', 'Deux'], ['c', 'suggested', 'Trois'], ['d', 'untranslated', null]], $this->describe($result));
        self::assertSame(['a', 'b', 'c'], $result->applied);
        self::assertSame([], $result->skipped);
    }

    public function testSkipsWhatItCannotApply(): void
    {
        $segments = [new Segment('a', 'One'), new Segment('b', 'Two <b>now</b>'), new Segment('c', 'Three')->translate('Trois'), new Segment('d', 'Four')->suggest('Quatre'), new Segment('e', 'Five')->translate('Cinq')];
        $result = XliffMerge::merge($segments, $this->file([
            new XliffUnit('z', 'Other', 'Autre', SegmentState::Translated),
            new XliffUnit('b', 'Two then', 'Deux', SegmentState::Translated),
            new XliffUnit('a', 'One'),
            new XliffUnit('a', 'One', ' <br> ', SegmentState::Suggested),
            new XliffUnit('c', 'Three', 'Trois', SegmentState::Translated),
            new XliffUnit('d', 'Four', ' Quatre', SegmentState::Suggested),
            new XliffUnit('e', 'Five', 'Cinq', SegmentState::Suggested),
        ]));

        self::assertSame([
            ['z', 'unknown-key'],
            ['b', 'source-changed'],
            ['a', 'no-target'],
            ['a', 'no-target'],
            ['c', 'unchanged'],
            ['d', 'unchanged'],
            ['e', 'unchanged'],
        ], array_map(static fn (Skipped $skipped): array => [$skipped->key, $skipped->reason->value], $result->skipped));
        self::assertSame([], $result->applied);
        self::assertSame([['a', 'untranslated', null], ['b', 'untranslated', null], ['c', 'translated', 'Trois'], ['d', 'suggested', 'Quatre'], ['e', 'translated', 'Cinq']], $this->describe($result));
    }

    public function testATargetInAnotherStateOrWithOtherWordsIsApplied(): void
    {
        $segments = [new Segment('a', 'One')->translate('Un'), new Segment('b', 'Two')->translate('Deux'), new Segment('c', 'Three')->withSource('Three')];
        $result = XliffMerge::merge($segments, $this->file([
            new XliffUnit('a', 'One', 'Un', SegmentState::Reviewed),
            new XliffUnit('b', 'Two', 'Deux !', SegmentState::Translated),
            new XliffUnit('c', ' Three ', 'Trois', SegmentState::Translated),
        ]));

        self::assertSame([['a', 'reviewed', 'Un'], ['b', 'translated', 'Deux !'], ['c', 'translated', 'Trois']], $this->describe($result));
    }

    public function testComparesTheSourcesByTheirWords(): void
    {
        $segments = [new Segment('a', 'Tom &amp; Jerry&nbsp;!')];
        $result = XliffMerge::merge($segments, new XliffReader()->read(new XliffWriter()->write($this->file([new XliffUnit('a', 'Tom &amp; Jerry&nbsp;!', 'Tom et Jerry', SegmentState::Translated)]))));

        self::assertSame(['a'], $result->applied);
    }

    public function testLeavesOutTheTagsTheSourceDoesNotHold(): void
    {
        $result = XliffMerge::merge([new Segment('a', 'A <b>bold</b> word')], $this->file([new XliffUnit('a', 'A <b>bold</b> word', 'Un mot <b>gras</b> <img src="x" onerror="y">', SegmentState::Translated)]));

        self::assertSame('Un mot <b>gras</b> ', $result->segments[0]->target);
    }

    public function testRefusesAFileOfOtherLanguages(): void
    {
        try {
            XliffMerge::merge([], $this->file([]), LanguageTag::of('de'));
            self::fail('The source language was accepted.');
        } catch (InvalidLanguage $invalidLanguage) {
            self::assertSame('The source language is "de", but "en" was given.', $invalidLanguage->getMessage());
        }

        try {
            XliffMerge::merge([], $this->file([]), LanguageTag::of('en'), LanguageTag::of('fr-CA'));
            self::fail('The target language was accepted.');
        } catch (InvalidLanguage $invalidLanguage) {
            self::assertSame('The target language is "fr-CA", but "fr" was given.', $invalidLanguage->getMessage());
        }

        $this->expectException(InvalidLanguage::class);
        $this->expectExceptionMessageIsOrContains('The target language is "fr", but "none" was given.');

        XliffMerge::merge([], new XliffFile(LanguageTag::of('en'), null, 'd'), targetLanguage: LanguageTag::of('fr'));
    }

    public function testAcceptsAFileWithoutTargetLanguageWhenNoneIsAsked(): void
    {
        self::assertSame([], XliffMerge::merge([], new XliffFile(LanguageTag::of('en'), null, 'd'))->segments);
    }

    /**
     * @param list<XliffUnit> $units
     */
    private function file(array $units): XliffFile
    {
        return new XliffFile(LanguageTag::of('en'), LanguageTag::of('fr'), 'd', $units);
    }

    /**
     * @return list<array{string, string, ?string}>
     */
    private function describe(MergeResult $result): array
    {
        return array_map(static fn (Segment $segment): array => [$segment->key, $segment->state->value, $segment->target], $result->segments);
    }
}
