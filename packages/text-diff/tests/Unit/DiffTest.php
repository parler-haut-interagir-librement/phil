<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit;

use Phil\TextDiff\Change;
use Phil\TextDiff\ChangeKind;
use Phil\TextDiff\Diff;
use Phil\TextDiff\Exception\InvalidConfiguration;
use Phil\TextDiff\Exception\TextDiffException;
use Phil\TextDiff\Operation;
use Phil\TextDiff\OperationKind;
use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextDiff::class)]
#[CoversClass(Diff::class)]
#[CoversClass(Change::class)]
#[CoversClass(InvalidConfiguration::class)]
final class DiffTest extends TestCase
{
    public function testComparesWordsByDefault(): void
    {
        $diff = new TextDiff()->compare('The quick brown fox', 'The slow brown dog');

        self::assertSame(Tokenizer::Words, $diff->tokenizer);
        self::assertSame(['=The| ', '-quick', '+slow', '= |brown| ', '-fox', '+dog'], Operations::describe($diff->operations));
    }

    public function testEachGranularityHasItsShortcut(): void
    {
        $differ = new TextDiff();

        self::assertSame(['=one| ', '-two', '+2'], Operations::describe($differ->words('one two', 'one 2')->operations));
        self::assertSame(Tokenizer::Words, $differ->words('a', 'a')->tokenizer);
        self::assertSame(['=a b|'."\n", '-c d', '+c e'], Operations::describe($differ->lines("a b\nc d", "a b\nc e")->operations));
        self::assertSame(Tokenizer::Lines, $differ->lines('a', 'a')->tokenizer);
        self::assertSame(['=One.|'."\n\n", '-Two.', '+Two!'], Operations::describe($differ->paragraphs("One.\n\nTwo.", "One.\n\nTwo!")->operations));
        self::assertSame(Tokenizer::Paragraphs, $differ->paragraphs('a', 'a')->tokenizer);
        self::assertSame(['=<p>', '-a', '+b', '=</p>'], Operations::describe($differ->html('<p>a</p>', '<p>b</p>')->operations));
        self::assertSame(Tokenizer::Html, $differ->html('a', 'a')->tokenizer);
        self::assertSame(Tokenizer::Lines, $differ->compare('a', 'b', Tokenizer::Lines)->tokenizer);
    }

    public function testTheBoundOfEditsIsKept(): void
    {
        self::assertSame(['-a| |b', '+c| |d'], Operations::describe(new TextDiff(0)->words('a b', 'c d')->operations));
        self::assertSame(['-a', '+c', '= |x| ', '-b', '+d'], Operations::describe(new TextDiff(4)->words('a x b', 'c x d')->operations));
        self::assertSame(['-a| |x| |b', '+c| |x| |d'], Operations::describe(new TextDiff(3)->words('a x b', 'c x d')->operations));
    }

    public function testTheDefaultBoundIsAThousandEdits(): void
    {
        $old = str_repeat(',', 250).'M'.str_repeat(',', 250);
        $new = str_repeat(';', 250).'M'.str_repeat(';', 250);

        self::assertCount(5, new TextDiff()->words($old, $new)->operations);
        self::assertCount(2, new TextDiff()->words($old, $new.';')->operations);
    }

    public function testRefusesANegativeBound(): void
    {
        try {
            new TextDiff(-1);
            self::fail('A negative bound was accepted.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertInstanceOf(TextDiffException::class, $invalidConfiguration);
            self::assertSame('The number of edits searched for cannot be negative; got -1.', $invalidConfiguration->getMessage());
        }
    }

    public function testGivesBothTextsBack(): void
    {
        $diff = new TextDiff()->words('Un été, déjà fini.', 'Un hiver, déjà là.');

        self::assertSame('Un été, déjà fini.', $diff->old());
        self::assertSame('Un hiver, déjà là.', $diff->new());
        self::assertFalse($diff->isIdentical());
        self::assertTrue(new TextDiff()->words('same', 'same')->isIdentical());
        self::assertTrue(new TextDiff()->words('', '')->isIdentical());
        self::assertFalse(new TextDiff()->words('', 'x')->isIdentical());
        self::assertFalse(new TextDiff()->words('x', '')->isIdentical());
    }

    public function testListsTheChangesWithTheirOffsetsInCodePoints(): void
    {
        $changes = new TextDiff()->words('Été: un deux trois quatre', 'Été: zéro un trois cinq')->changes();

        self::assertEquals([
            new Change(ChangeKind::Insertion, '', 'zéro ', 5, 5),
            new Change(ChangeKind::Deletion, 'deux ', '', 8, 13),
            new Change(ChangeKind::Replacement, 'quatre', 'cinq', 19, 19),
        ], $changes);
    }

    public function testScriptsWithoutSpacesAreComparedCharacterByCharacter(): void
    {
        self::assertEquals([new Change(ChangeKind::Replacement, '猫', '犬', 2, 2)], new TextDiff()->words('私は猫が好き', '私は犬が好き')->changes());
        self::assertEquals([new Change(ChangeKind::Insertion, '', 'มาก', 6, 6)], new TextDiff()->words('ฉันชอบ', 'ฉันชอบมาก')->changes());
        self::assertEquals([new Change(ChangeKind::Replacement, 'cat', 'dog', 4, 4)], new TextDiff()->words('猫 = cat', '猫 = dog')->changes());
    }

    public function testADeletionAtTheEndOrBeforeKeptTextIsNoReplacement(): void
    {
        self::assertEquals([new Change(ChangeKind::Deletion, ' b', '', 1, 1)], new TextDiff()->words('a b', 'a')->changes());
        self::assertEquals([new Change(ChangeKind::Deletion, 'é ', '', 0, 0), new Change(ChangeKind::Insertion, '', ' ü', 5, 3)], new TextDiff()->words('é a b', 'a b ü')->changes());
        self::assertSame([], new TextDiff()->words('a', 'a')->changes());
    }

    public function testAReplacementCountsTheInsertedTextOnTheNewSide(): void
    {
        self::assertEquals([
            new Change(ChangeKind::Replacement, 'ab', 'éèêë', 0, 0),
            new Change(ChangeKind::Replacement, 'c', 'd', 5, 7),
        ], new TextDiff()->words('ab x c', 'éèêë x d')->changes());
        self::assertEquals([
            new Change(ChangeKind::Replacement, 'ab', 'éèêë', 2, 2),
            new Change(ChangeKind::Replacement, 'c', 'd', 7, 9),
        ], new TextDiff()->words('k ab x c', 'k éèêë x d')->changes());
    }

    public function testAnOperationListIsAValue(): void
    {
        $diff = new Diff([new Operation(OperationKind::Insert, ['a'])], Tokenizer::Html);

        self::assertSame('a', $diff->new());
        self::assertSame('', $diff->old());
        self::assertEquals([new Change(ChangeKind::Insertion, '', 'a', 0, 0)], $diff->changes());
    }
}
