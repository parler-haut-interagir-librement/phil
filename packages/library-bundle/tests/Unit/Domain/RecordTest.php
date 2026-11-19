<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Domain;

use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Member;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Record::class)]
#[CoversClass(Member::class)]
#[CoversClass(InvalidLibraryData::class)]
final class RecordTest extends TestCase
{
    public function testARecordKeepsItsValuesTrimmed(): void
    {
        $record = new Record(TitleKind::Game, '  Azul ', '978-0-306-40615-7', ' Michael Kiesling ', ' Plan B ', 2017, 2, 4, 30, 45, 8, 'en-CA', "  Tiles.\nOn a board.  ");

        self::assertSame([TitleKind::Game, 'Azul', '9780306406157', 'Michael Kiesling', 'Plan B', 2017, 2, 4, 30, 45, 8, 'en-CA', "Tiles.\nOn a board."], [$record->kind, $record->name, $record->isbn, $record->creators, $record->publisher, $record->year, $record->minPlayers, $record->maxPlayers, $record->minMinutes, $record->maxMinutes, $record->minAge, $record->language, $record->summary]);
    }

    public function testEmptyTextsAreNone(): void
    {
        $record = new Record(TitleKind::Book, 'Dune', ' ', ' ', '', summary: '   ');

        self::assertSame([null, null, null, null, null, null], [$record->isbn, $record->creators, $record->publisher, $record->summary, $record->year, $record->language]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function isbns(): iterable
    {
        yield 'an ISBN-13' => ['9780306406157', '9780306406157'];
        yield 'an ISBN-13 with hyphens and spaces' => [' 978-0 306-40615-7 ', '9780306406157'];
        yield 'an ISBN-10' => ['0-306-40615-2', '9780306406157'];
        yield 'an ISBN-10 ending in X' => ['0-8044-2957-x', '9780804429573'];
        yield 'an ISBN-10 whose ISBN-13 ends in 0' => ['0-19-852663-6', '9780198526636'];
        yield 'another ISBN-10' => ['2-07-036822-X', '9782070368228'];
        yield 'an ISBN-10 whose ISBN-13 ends in 9' => ['0010000003', '9780010000009'];
        yield 'an ISBN-10 whose ISBN-13 ends in 0 for real' => ['0010000747', '9780010000740'];
        yield 'an ISBN framed by white space' => ["\t9780306406157\n", '9780306406157'];
    }

    #[DataProvider('isbns')]
    public function testAnIsbnIsKeptAsIsbn13(string $isbn, string $thirteen): void
    {
        self::assertSame($thirteen, Record::isbn($isbn));
    }

    public function testAnIsbn10WhoseSumIsARoundNumber(): void
    {
        // 978 + 000000000: 9 + 21 + 8 = 38, so the check digit is 2.
        self::assertSame('9780000000002', Record::isbn('0000000000'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIsbns(): iterable
    {
        yield 'a wrong check digit' => ['9780306406158'];
        yield 'a wrong ISBN-10' => ['0306406153'];
        yield 'too short' => ['978030640615'];
        yield 'letters' => ['97803064O6157'];
        yield 'too long' => ['97803064061570'];
        yield 'an X inside' => ['03064X6152'];
        yield 'a letter before' => ['A9780306406157'];
        yield 'a letter after' => ['9780306406157Z'];
        yield 'a letter after an ISBN-10' => ['0306406152Z'];
        yield 'a letter before zeros' => ['Z0000000000'];
    }

    #[DataProvider('notIsbns')]
    public function testANumberThatIsNotAnIsbnIsRefused(string $isbn): void
    {
        $this->expectExceptionObject(InvalidLibraryData::because(\sprintf('"%s" is not an ISBN-10 or ISBN-13.', $isbn)));

        Record::isbn($isbn);
    }

    public function testTheMessageOfALongIsbnIsCut(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('9', 19).'…" is not an ISBN-10 or ISBN-13.');

        Record::isbn(str_repeat('9', 30));
    }

    /**
     * @return iterable<string, array{array{name?: string, creators?: string, publisher?: string, summary?: string, year?: int, minPlayers?: int, maxPlayers?: int, minMinutes?: int, maxMinutes?: int, minAge?: int, language?: string}, string}>
     */
    public static function badRecords(): iterable
    {
        yield 'no name' => [['name' => '  '], 'A record has a name.'];
        yield 'a long name' => [['name' => str_repeat('a', 256)], 'The name of a record is a text of at most 255 characters, without control characters.'];
        yield 'a tab in the name' => [['name' => "a\tb"], 'The name of a record is a text of at most 255 characters, without control characters.'];
        yield 'a control in the summary' => [['summary' => "a\x07b"], 'The summary of a record is a text of at most 5000 characters, without control characters.'];
        yield 'a long summary' => [['summary' => str_repeat('a', 5001)], 'The summary of a record is a text of at most 5000 characters, without control characters.'];
        yield 'long creators' => [['creators' => str_repeat('a', 256)], 'The creators of a record is a text of at most 255 characters, without control characters.'];
        yield 'a newline in the publisher' => [['publisher' => "a\nb"], 'The publisher of a record is a text of at most 255 characters, without control characters.'];
        yield 'a year too early' => [['year' => 999], 'The year of a record is from 1000 to 9999, not 999.'];
        yield 'a year too late' => [['year' => 10_000], 'The year of a record is from 1000 to 9999, not 10000.'];
        yield 'no player' => [['minPlayers' => 0], 'The minimum of players of a record is from 1 to 99, not 0.'];
        yield 'too many players' => [['maxPlayers' => 100], 'The maximum of players of a record is from 1 to 99, not 100.'];
        yield 'no minute' => [['minMinutes' => 0], 'The shortest play of a record is from 1 to 10080, not 0.'];
        yield 'more than a week' => [['maxMinutes' => 10_081], 'The longest play of a record is from 1 to 10080, not 10081.'];
        yield 'a negative age' => [['minAge' => -1], 'The youngest age of a record is from 0 to 99, not -1.'];
        yield 'an age too high' => [['minAge' => 100], 'The youngest age of a record is from 0 to 99, not 100.'];
        yield 'players upside down' => [['minPlayers' => 5, 'maxPlayers' => 4], 'The players of a record goes from its minimum to its maximum: 5 is above 4.'];
        yield 'minutes upside down' => [['minMinutes' => 60, 'maxMinutes' => 59], 'The length of a play of a record goes from its minimum to its maximum: 60 is above 59.'];
        yield 'a language that is not a tag' => [['language' => 'French'], 'The language of a record is a language tag, such as "fr" or "en-CA".'];
        yield 'a language with a newline' => [['language' => "fr\n"], 'The language of a record is a language tag, such as "fr" or "en-CA".'];
        yield 'not UTF-8' => [['name' => "\xff"], 'The name of a record is a text of at most 255 characters, without control characters.'];
    }

    /**
     * @param array{name?: string, creators?: string, publisher?: string, summary?: string, year?: int, minPlayers?: int, maxPlayers?: int, minMinutes?: int, maxMinutes?: int, minAge?: int, language?: string} $values
     */
    #[DataProvider('badRecords')]
    public function testABadRecordIsRefused(array $values, string $message): void
    {
        $this->expectExceptionObject(InvalidLibraryData::because($message));

        new Record(TitleKind::Game, ...['name' => 'Azul', ...$values]);
    }

    public function testTheBoundsAreKept(): void
    {
        $record = new Record(TitleKind::Game, str_repeat('é', 255), creators: str_repeat('é', 255), year: 1000, minPlayers: 1, maxPlayers: 99, minMinutes: 1, maxMinutes: 10_080, minAge: 0, language: 'zh-Hant-TW', summary: str_repeat('é', 4998)."\tx\r\n");
        self::assertSame([1000, 1, 99, 1, 10_080, 0], [$record->year, $record->minPlayers, $record->maxPlayers, $record->minMinutes, $record->maxMinutes, $record->minAge]);
        self::assertSame(9999, new Record(TitleKind::Game, 'a', year: 9999, minPlayers: 3, maxPlayers: 3, minAge: 99)->year);
    }

    public function testAGroupFitsThePlayers(): void
    {
        $record = new Record(TitleKind::Game, 'Azul', minPlayers: 2, maxPlayers: 4);

        self::assertFalse($record->fits(1));
        self::assertTrue($record->fits(2));
        self::assertTrue($record->fits(4));
        self::assertFalse($record->fits(5));
        self::assertTrue(new Record(TitleKind::Book, 'Dune')->fits(50));
        self::assertTrue(new Record(TitleKind::Game, 'Solo', minPlayers: 1)->fits(99));
        self::assertTrue(new Record(TitleKind::Game, 'Duo', maxPlayers: 2)->fits(1));
    }

    public function testAMemberIsAStableIdentifier(): void
    {
        self::assertSame('card-0042', Member::check('card-0042'));
        self::assertTrue(Member::fits(str_repeat('a', 128)));
        self::assertTrue(Member::fits('9'));

        foreach (['', 'jo@example.com', str_repeat('a', 129), '-a', "a\n", 'a b'] as $bad) {
            self::assertFalse(Member::fits($bad));
        }

        $this->expectExceptionObject(InvalidLibraryData::because('A member is named by a stable identifier of 1 to 128 letters, digits, dots, underscores, colons or hyphens, never by an e-mail address.'));
        Member::check('jo@example.com');
    }
}
