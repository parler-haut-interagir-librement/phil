<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Check;

use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\CheckIssue;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Check\Punctuation;
use Phil\TranslationMemory\Check\SameAsSource;
use Phil\TranslationMemory\Check\Spaces;
use Phil\TranslationMemory\Check\Tags;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checked::class)]
#[CoversClass(CheckIssue::class)]
#[CoversClass(Punctuation::class)]
#[CoversClass(Spaces::class)]
#[CoversClass(Tags::class)]
#[CoversClass(SameAsSource::class)]
final class ChecksTest extends TestCase
{
    public function testNamesEachCheck(): void
    {
        self::assertSame(['punctuation', 'spaces', 'tags', 'same'], array_map(static fn (Check $check): string => $check->name(), [new Punctuation(), new Spaces(), new Tags(), new SameAsSource()]));
    }

    public function testReadsTheTargetOfASegment(): void
    {
        $flags = new Flags(['ignore-tags']);
        $checked = Checked::segment(new Segment('k', '<b>Hi</b>', SegmentState::Suggested, 'Salut'), LanguageTag::of('en'), LanguageTag::of('fr'), $flags);

        self::assertInstanceOf(Checked::class, $checked);
        self::assertSame(['<b>Hi</b>', 'Salut', 'en', 'fr', $flags], [$checked->source, $checked->target, $checked->sourceLanguage->value, $checked->targetLanguage->value, $checked->flags]);
        self::assertNull(Checked::segment(new Segment('k', 'Hi'), LanguageTag::of('en'), LanguageTag::of('fr')));
        self::assertSame([], Checked::segment(new Segment('k', 'Hi', SegmentState::Translated, 'Yo'), LanguageTag::of('en'), LanguageTag::of('fr'))?->flags->all());
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function punctuation(): iterable
    {
        yield 'same stop' => ['The end.', 'La fin.', null];
        yield 'French spaces' => ['Ready?', 'Prêt ?', null];
        yield 'narrow no-break space' => ['Ready!', "Prêt\u{202F}!", null];
        yield 'closing quote' => ['He said “go.”', 'Il a dit « va. »', null];
        yield 'full width' => ['Done.', '完成。', null];
        yield 'full width question' => ['Why?', '为什么？', null];
        yield 'three dots' => ['Wait…', 'Attends...', null];
        yield 'tags after' => ['<b>Go!</b>', '<b>Va !</b> ', null];
        yield 'no mark on both' => ['Title', 'Titre', null];
        yield 'colon' => ['Note:', 'Remarque :', null];
        yield 'semicolon' => ['One;', 'Un ;', null];
        yield 'comma' => ['One,', 'Un、', null];
        yield 'missing' => ['The end.', 'La fin', 'The source ends with “.”, the target with no punctuation.'];
        yield 'added' => ['Title', 'Titre.', 'The source ends with no punctuation, the target with “.”.'];
        yield 'another' => ['Ready?', 'Prêt !', 'The source ends with “?”, the target with “!”.'];
        yield 'bracket' => ['(see below)', '(voir plus bas.)', 'The source ends with no punctuation, the target with “.”.'];
    }

    #[DataProvider('punctuation')]
    public function testChecksTheFinalPunctuation(string $source, string $target, ?string $issue): void
    {
        self::assertSame(null === $issue ? [] : [['punctuation', $issue]], $this->issues(new Punctuation(), $source, $target));
    }

    public function testReadsTheCharacterReferencesOfHtmlFive(): void
    {
        self::assertSame([['spaces', 'The source ends with white space, the target does not.']], $this->issues(new Spaces(), 'One&NewLine;', 'Un'));
    }

    public function testChecksTheSpacesAroundAndInside(): void
    {
        self::assertSame([], $this->issues(new Spaces(), ' One two ', ' Un deux '));
        self::assertSame([], $this->issues(new Spaces(), 'One  two', 'Un  deux'));
        self::assertSame([], $this->issues(new Spaces(), '<b>One</b> two', '<b>Un</b> deux'));
        self::assertSame([['spaces', 'The source starts with white space, the target does not.']], $this->issues(new Spaces(), ' One', 'Un'));
        self::assertSame([['spaces', 'The target ends with white space, the source does not.']], $this->issues(new Spaces(), 'One', 'Un&#32;'));
        self::assertSame([
            ['spaces', 'The target starts with white space, the source does not.'],
            ['spaces', 'The source ends with white space, the target does not.'],
            ['spaces', 'The target holds two spaces in a row, the source none.'],
        ], $this->issues(new Spaces(), 'One two ', ' Un  deux'));
    }

    public function testChecksTheTagsOfTheSource(): void
    {
        self::assertSame([], $this->issues(new Tags(), 'A <b>bold</b> <br>word', '<br>Un mot <b>gras</b>'));
        self::assertSame([], $this->issues(new Tags(), 'No tags', 'Aucune balise'));
        self::assertSame([
            ['tags', 'The target lacks the tag </b>.'],
            ['tags', 'The target holds the tag <i>, which the source does not.'],
        ], $this->issues(new Tags(), '<b>bold</b>', '<b>gras <i>x'));
        self::assertSame([['tags', 'The target lacks the tag <br>.']], $this->issues(new Tags(), 'a<br>b<br>c', 'a<br>bc'));
        self::assertSame([['tags', 'The target holds the tag <br>, which the source does not.']], $this->issues(new Tags(), 'a<br>bc', 'a<br>b<br>c'));
        self::assertSame([
            ['tags', 'The target lacks the tag <a href="/x">.'],
            ['tags', 'The target holds the tag <a href="/y">, which the source does not.'],
        ], $this->issues(new Tags(), '<a href="/x">x</a>', '<a href="/y">x</a>'));
    }

    public function testFindsATargetIdenticalToItsSource(): void
    {
        self::assertSame([['same', 'The target is the same as the source.']], $this->issues(new SameAsSource(), 'Hit <b>points</b>', 'Hit points'));
        self::assertSame([], $this->issues(new SameAsSource(), 'Hit points', 'Points de vie'));
        self::assertSame([], $this->issues(new SameAsSource(), 'Hit points', 'hit points'));
        self::assertSame([], $this->issues(new SameAsSource(), '42 — 7', '42 — 7'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function issues(Check $check, string $source, string $target): array
    {
        return array_map(static fn (CheckIssue $issue): array => [$issue->check, $issue->message], $check->check(new Checked($source, $target, LanguageTag::of('en'), LanguageTag::of('fr'))));
    }
}
