<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Dbal;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use JsonException;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Dbal\DbalProposalStore;
use Phil\ProposalReview\Dbal\DbalReviewStore;
use Phil\ProposalReview\Dbal\DbalVoteLog;
use Phil\ProposalReview\Dbal\Tables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * What a read does before it asks the database: an identifier a database could not have kept is answered at once, as
 * SQLite alone would find nothing for it while MySQL's collations would find the rows of the same text without its
 * last spaces. A connection that refuses every query tells the guard from the answer of the database.
 */
#[CoversClass(DbalProposalStore::class)]
#[CoversClass(DbalReviewStore::class)]
#[CoversClass(DbalVoteLog::class)]
#[CoversClass(Tables::class)]
final class ReadsOfUnstorableIdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(Connection, string, string): mixed, mixed, int}>
     */
    public static function reads(): iterable
    {
        yield 'a proposal' => [static fn (Connection $connection, string $first, string $second): mixed => new DbalProposalStore($connection)->get($first, $second), null, 2];
        yield 'the proposals of a campaign' => [static fn (Connection $connection, string $first): mixed => new DbalProposalStore($connection)->ofCampaign($first), [], 1];
        yield 'the reviewers of a proposal' => [static fn (Connection $connection, string $first, string $second): mixed => new DbalProposalStore($connection)->reviewersOf($first, $second), [], 2];
        yield 'the assignments of a campaign' => [static fn (Connection $connection, string $first): mixed => new DbalProposalStore($connection)->assignmentCounts($first), [], 1];
        yield 'the conflicts of a reviewer' => [static fn (Connection $connection, string $first, string $second): mixed => new DbalProposalStore($connection)->conflictsOf($first, $second), [], 2];
        yield 'the reviews of a proposal' => [static fn (Connection $connection, string $first, string $second): mixed => new DbalReviewStore($connection)->ofProposal($first, $second), [], 2];
        yield 'the decisions of a campaign' => [static fn (Connection $connection, string $first): mixed => new DbalReviewStore($connection)->decisions($first), [], 1];
        yield 'the votes of a campaign' => [static fn (Connection $connection, string $first): mixed => new DbalVoteLog($connection)->ofCampaign($first), [], 1];
    }

    /**
     * @param Closure(Connection, string, string): mixed $read
     */
    #[DataProvider('reads')]
    public function testAnUnstorableIdentifierIsAnsweredWithoutAskingTheDatabase(Closure $read, mixed $empty, int $arity): void
    {
        $pairs = [['a ', 'b'], ["a\xC3", 'b']];

        if (2 === $arity) {
            $pairs[] = ['a', 'b '];
            $pairs[] = ['a ', 'b '];
            $pairs[] = ['a', "b\xC3"];
        }

        foreach ($pairs as [$first, $second]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::never())->method(self::anything());

            self::assertSame($empty, $read($connection, $first, $second));
        }
    }

    public function testTheReviewersOfAProposalAreSortedWhateverOrderTheDatabaseGivesThem(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['r2', 'R1', '7', 'r1']);

        self::assertSame(['7', 'R1', 'r1', 'r2'], new DbalProposalStore($connection)->reviewersOf('cfp', 'a'));
    }

    public function testTheReviewersOfAProposalMustBeText(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([7]);

        $this->expectExceptionObject(new UnexpectedValueException('The stored reviewer of an assignment is not text.'));

        new DbalProposalStore($connection)->reviewersOf('cfp', 'a');
    }

    public function testTheAssignmentsAreSortedByReviewerWhateverOrderTheDatabaseGivesThem(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAllNumeric')->willReturn([['r2', '1'], ['r1', 2], ['R1', '3']]);

        self::assertSame(['R1' => 3, 'r1' => 2, 'r2' => 1], new DbalProposalStore($connection)->assignmentCounts('cfp'));
        self::assertSame(['R1', 'r1', 'r2'], array_keys(new DbalProposalStore($connection)->assignmentCounts('cfp')));
    }

    public function testTheConflictsAreSortedByKindThenTargetWhateverOrderTheDatabaseGivesThem(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['kind' => 'author', 'target' => 'b'],
            ['kind' => 'proposal', 'target' => 'b'],
            ['kind' => 'author', 'target' => 'a'],
            ['kind' => 'proposal', 'target' => 'a'],
        ]);

        self::assertEquals([
            ConflictOfInterest::withProposal('cfp', 'r', 'a'),
            ConflictOfInterest::withProposal('cfp', 'r', 'b'),
            ConflictOfInterest::withAuthor('cfp', 'r', 'a'),
            ConflictOfInterest::withAuthor('cfp', 'r', 'b'),
        ], new DbalProposalStore($connection)->conflictsOf('cfp', 'r'));
    }

    public function testAConflictIsKeptUnderItsProposalOrItsAuthor(): void
    {
        $rows = [];
        $connection = self::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('insert')->willReturnCallback(static function (string $table, array $row) use (&$rows): int {
            $rows[] = $row;

            return 1;
        });
        $connection->method('transactional')->willReturnCallback(static fn (Closure $work): mixed => $work($connection));

        $store = new DbalProposalStore($connection);
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r', 'p'));
        $store->declareConflict(ConflictOfInterest::withAuthor('cfp', 'r', 'u'));

        self::assertSame(['proposal', 'p'], [$rows[0]['kind'], $rows[0]['target']]);
        self::assertSame(['author', 'u'], [$rows[1]['kind'], $rows[1]['target']]);
    }

    public function testAWordColumnCannotBeNullByDefaultAndMaySayOtherwise(): void
    {
        $platform = new SQLitePlatform();

        self::assertTrue(Tables::wordColumn('kind', 10, $platform)->getNotnull());
        self::assertFalse(Tables::wordColumn('kind', 10, $platform, false)->getNotnull());
    }

    public function testAValueIsEncodedWithItsSlashesItsLettersAndItsFractionsAsTheyAre(): void
    {
        self::assertSame('["a/b","é",1.0,{"k":"v"}]', Tables::encode(['a/b', 'é', 1.0, ['k' => 'v']]));
    }

    public function testAValueThatCannotBeEncodedThrows(): void
    {
        $this->expectException(JsonException::class);

        Tables::encode(["\xC3"]);
    }

    public function testAStoredValueIsReadDownToSevenLevelsOfLists(): void
    {
        self::assertSame([[[[[[[]]]]]]], Tables::decode(str_repeat('[', 7).str_repeat(']', 7), 'value'));

        $this->expectExceptionObject(new UnexpectedValueException('The stored value is not JSON.'));

        Tables::decode(str_repeat('[', 8).str_repeat(']', 8), 'value');
    }

    #[DataProvider('numbersNotWhole')]
    public function testANumberReadAsTextIsWholeFromItsFirstCharacter(string $text): void
    {
        $this->expectException(UnexpectedValueException::class);

        Tables::integer($text, 'number');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function numbersNotWhole(): iterable
    {
        yield 'a letter first' => ['x5'];
        yield 'a space first' => [' 5'];
        yield 'a sign in the middle' => ['5-5'];
    }
}
