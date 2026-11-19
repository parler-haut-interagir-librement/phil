<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Dbal;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Dbal\DbalCampaignStore;
use Phil\ProposalReview\Dbal\DbalProposalStore;
use Phil\ProposalReview\Dbal\DbalReviewStore;
use Phil\ProposalReview\Dbal\DbalVoteLog;
use Phil\ProposalReview\Dbal\Tables;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Pairwise\Vote;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\Score;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use Phil\ProposalReview\Tests\Unit\Fixture\RacingWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

/**
 * The stores on SQLite in memory: the contract, their tables, and rows the stores did not write.
 */
#[CoversClass(DbalCampaignStore::class)]
#[CoversClass(DbalProposalStore::class)]
#[CoversClass(DbalReviewStore::class)]
#[CoversClass(DbalVoteLog::class)]
#[CoversClass(Tables::class)]
final class SqliteStoreTest extends DbalStoreTestCase
{
    private const string SCHEDULE = '[{"phase":"review","start":"2026-11-10T00:00:00.000000Z","end":"2026-11-20T00:00:00.000000Z"},{"phase":"deliberation","start":"2026-11-20T00:00:00.000000Z","end":"2026-11-30T00:00:00.000000Z"}]';

    private const string POLICY = '{"blind":true,"minReviews":2,"maxReviews":null,"aggregation":"mean","visibility":"after-own-review","assignedOnly":true}';

    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }

    public function testTheTablesAreKeyedForTheirReads(): void
    {
        $schema = $this->connection()->createSchemaManager()->introspectSchema();
        $keys = [
            'campaign' => ['id'],
            'proposal' => ['campaign', 'id'],
            'assignment' => ['campaign', 'proposal', 'reviewer'],
            'conflict' => ['campaign', 'reviewer', 'kind', 'target'],
            'review' => ['campaign', 'proposal', 'reviewer'],
            'decision' => ['campaign', 'proposal'],
            'vote' => ['campaign', 'sequence'],
        ];

        foreach ($keys as $table => $columns) {
            self::assertSame($columns, array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('phil_review_'.$table)->getPrimaryKeyConstraint()?->getColumnNames() ?? []), $table);
        }

        self::assertSame(['id', 'definition', 'observed_phase'], $this->columns($schema, 'campaign'));
        self::assertSame(['campaign', 'id', 'authors'], $this->columns($schema, 'proposal'));
        self::assertSame(['campaign', 'reviewer', 'kind', 'target'], $this->columns($schema, 'conflict'));
        self::assertSame(['campaign', 'proposal', 'reviewer', 'scores', 'submitted_at'], $this->columns($schema, 'review'));
        self::assertSame(['campaign', 'proposal', 'decision', 'recorded_by', 'recorded_at', 'applied_at'], $this->columns($schema, 'decision'));
        self::assertSame(['campaign', 'sequence', 'voter', 'winner', 'loser', 'cast_at'], $this->columns($schema, 'vote'));

        $decision = $schema->getTable('phil_review_decision');
        self::assertFalse($decision->getColumn('applied_at')->getNotnull());
        self::assertTrue($decision->getColumn('recorded_at')->getNotnull());
        self::assertSame(27, $decision->getColumn('recorded_at')->getLength());
        self::assertSame(16, $decision->getColumn('decision')->getLength());
        self::assertSame(255, $decision->getColumn('recorded_by')->getLength());
        self::assertSame(8, $schema->getTable('phil_review_conflict')->getColumn('kind')->getLength());
        self::assertSame(16, $schema->getTable('phil_review_campaign')->getColumn('observed_phase')->getLength());
    }

    public function testTheTablesCanBePrefixedAndATableAlreadyThereIsKept(): void
    {
        $schema = $this->schema('my_');

        foreach (['campaign', 'proposal', 'assignment', 'conflict', 'review', 'decision', 'vote'] as $table) {
            self::assertTrue($schema->hasTable('my_'.$table), $table);
        }

        $connection = $this->connection();
        self::assertSame($schema, new DbalCampaignStore($connection, 'my_')->configureSchema($schema));
        self::assertSame($schema, new DbalProposalStore($connection, 'my_')->configureSchema($schema));
        self::assertSame($schema, new DbalReviewStore($connection, 'my_')->configureSchema($schema));
        self::assertSame($schema, new DbalVoteLog($connection, 'my_')->configureSchema($schema));

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        new DbalProposalStore($connection, 'my_')->save(new Proposal('cfp', 'a', []));
        self::assertNotNull(new DbalProposalStore($connection, 'my_')->get('cfp', 'a'));
        self::assertNull(new DbalProposalStore($connection)->get('cfp', 'a'));
    }

    public function testATableMissingFromASchemaIsAddedBesideTheOthers(): void
    {
        $connection = $this->connection();
        $partial = Schema::editor()->create();
        $partial = new DbalReviewStore($connection, 'p_')->configureSchema($partial);
        $partial = new DbalProposalStore($connection, 'p_')->configureSchema($partial);

        self::assertSame(['p_assignment', 'p_conflict', 'p_decision', 'p_proposal', 'p_review'], $this->tableNames($partial));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPrefixes(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1_'];
        yield 'capitals' => ['Review_'];
        yield 'a statement' => ['x; DROP TABLE y; --'];
        yield 'a new line after' => ["review_\n"];
        yield 'too long' => [str_repeat('r', 41)];
    }

    #[DataProvider('invalidPrefixes')]
    public function testAPrefixIsChecked(string $prefix): void
    {
        $this->expectExceptionObject(InvalidInput::because('A table prefix is up to 40 lowercase letters, digits or "_", not starting with a digit.'));

        new DbalVoteLog($this->connection(), $prefix);
    }

    /**
     * @return iterable<string, array{class-string<DbalCampaignStore|DbalProposalStore|DbalReviewStore>}>
     */
    public static function otherStores(): iterable
    {
        yield 'campaigns' => [DbalCampaignStore::class];
        yield 'proposals' => [DbalProposalStore::class];
        yield 'reviews' => [DbalReviewStore::class];
    }

    /**
     * @param class-string<DbalCampaignStore|DbalProposalStore|DbalReviewStore> $store
     */
    #[DataProvider('otherStores')]
    public function testEveryStoreChecksItsPrefix(string $store): void
    {
        $this->expectException(InvalidInput::class);

        new $store($this->connection(), 'Bad');
    }

    public function testTheLongestPrefixIsTaken(): void
    {
        self::assertSame(str_repeat('r', 40), Tables::prefix(str_repeat('r', 40)));
        self::assertSame('_r1', Tables::prefix('_r1'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unstorableIdentifiers(): iterable
    {
        yield 'a space at the end' => ['a '];
        yield 'invalid UTF-8' => ["a\xC3"];
    }

    #[DataProvider('unstorableIdentifiers')]
    public function testAnIdentifierADatabaseCannotKeepIsRefused(string $id): void
    {
        $this->expectExceptionObject(InvalidInput::because('Identifiers kept in a database are valid UTF-8 and do not end with a space.'));

        new DbalProposalStore($this->connection())->save(new Proposal('cfp', $id, []));
    }

    /**
     * @return iterable<string, array{Closure(Connection, DateTimeImmutable): void}>
     */
    public static function writesOfUnstorableIdentifiers(): iterable
    {
        yield 'a campaign' => [static function (Connection $connection): void {
            new DbalCampaignStore($connection)->save(new Campaign('c ', Jury::schedule(), [new Criterion('a')]));
        }];
        yield 'a criterion' => [static function (Connection $connection): void {
            new DbalCampaignStore($connection)->save(new Campaign('c', Jury::schedule(), [new Criterion('a ')]));
        }];
        yield 'an author' => [static function (Connection $connection): void {
            new DbalProposalStore($connection)->save(new Proposal('c', 'p', ['a ']));
        }];
        yield 'an assigned reviewer' => [static function (Connection $connection): void {
            new DbalProposalStore($connection)->assign('c', 'p', 'r ');
        }];
        yield 'a conflict' => [static function (Connection $connection): void {
            new DbalProposalStore($connection)->declareConflict(ConflictOfInterest::withAuthor('c', 'r', 'a '));
        }];
        yield 'a reviewer' => [static function (Connection $connection, DateTimeImmutable $at): void {
            new DbalReviewStore($connection)->save(new Review('c', 'p', 'r ', [], $at));
        }];
        yield 'a scored criterion' => [static function (Connection $connection, DateTimeImmutable $at): void {
            new DbalReviewStore($connection)->save(new Review('c', 'p', 'r', [new Score('a ', 1)], $at));
        }];
        yield 'a decision maker' => [static function (Connection $connection, DateTimeImmutable $at): void {
            new DbalReviewStore($connection)->saveDecision(new DecisionRecord('c', 'p', Decision::Accepted, 'm ', $at));
        }];
        yield 'a voter' => [static function (Connection $connection, DateTimeImmutable $at): void {
            new DbalVoteLog($connection)->append('c', 'v ', 'a', 'b', $at);
        }];
    }

    /**
     * @param Closure(Connection, DateTimeImmutable): void $write
     */
    #[DataProvider('writesOfUnstorableIdentifiers')]
    public function testEveryWriteChecksItsIdentifiers(Closure $write): void
    {
        $this->expectException(InvalidInput::class);

        $write($this->connection(), new DateTimeImmutable('2026-11-12 10:00:00 UTC'));
    }

    public function testAnInstantOutsideTheYearsADatabaseKeepsIsRefused(): void
    {
        $this->expectExceptionObject(InvalidInput::because('Instants kept in a database fall within the years 1 to 9999.'));

        new DbalVoteLog($this->connection())->append('cfp', 'v', 'a', 'b', new DateTimeImmutable('9999-12-31 23:59:59 UTC')->modify('+1 second'));
    }

    public function testAnInstantOfTheYearZeroIsRefused(): void
    {
        $this->expectException(InvalidInput::class);

        new DbalVoteLog($this->connection())->append('cfp', 'v', 'a', 'b', new DateTimeImmutable('0000-06-01 00:00:00 UTC'));
    }

    public function testTheFirstAndLastYearsAreKept(): void
    {
        $log = new DbalVoteLog($this->connection());
        $log->append('cfp', 'v', 'a', 'b', new DateTimeImmutable('0001-01-01 00:00:00 UTC'));
        $log->append('cfp', 'v', 'a', 'b', new DateTimeImmutable('9999-12-31 23:59:59.999999 UTC'));

        self::assertSame(['0001-01-01', '9999-12-31'], array_map(static fn (Vote $vote): string => $vote->castAt->format('Y-m-d'), $log->ofCampaign('cfp')));
    }

    public function testAVoteTakesTheNextNumberWhenAnotherRequestTookIt(): void
    {
        [$connection, $racing] = $this->racingConnection();
        $racing->beforeTransactions(2, static function (DriverConnection $other): void {
            $other->exec("INSERT INTO phil_review_vote SELECT 'cfp', COALESCE(MAX(sequence), 0) + 1, 'early', 'a', 'b', '2026-11-12T10:00:00.000000Z' FROM phil_review_vote WHERE campaign = 'cfp'");
        });

        $vote = new DbalVoteLog($connection)->append('cfp', 'late', 'a', 'b', new DateTimeImmutable('2026-11-12 10:00:00 UTC'));

        self::assertSame(3, $vote->sequence);
        self::assertSame(['early', 'early', 'late'], array_map(static fn (Vote $vote): string => $vote->voter, new DbalVoteLog($connection)->ofCampaign('cfp')));
    }

    public function testAVoteGivesUpAfterItsAttempts(): void
    {
        [$connection, $racing] = $this->racingConnection();
        $racing->beforeTransactions(DbalVoteLog::MAX_ATTEMPTS, static function (DriverConnection $other): void {
            $other->exec("INSERT INTO phil_review_vote SELECT 'cfp', COALESCE(MAX(sequence), 0) + 1, 'early', 'a', 'b', '2026-11-12T10:00:00.000000Z' FROM phil_review_vote WHERE campaign = 'cfp'");
        });

        try {
            new DbalVoteLog($connection)->append('cfp', 'late', 'a', 'b', new DateTimeImmutable('2026-11-12 10:00:00 UTC'));
            self::fail('The vote was kept.');
        } catch (UniqueConstraintViolationException) {
            self::assertCount(DbalVoteLog::MAX_ATTEMPTS, new DbalVoteLog($connection)->ofCampaign('cfp'));
        }
    }

    public function testAVoteTakesItsLastAttempt(): void
    {
        [$connection, $racing] = $this->racingConnection();
        $racing->beforeTransactions(DbalVoteLog::MAX_ATTEMPTS - 1, static function (DriverConnection $other): void {
            $other->exec("INSERT INTO phil_review_vote SELECT 'cfp', COALESCE(MAX(sequence), 0) + 1, 'early', 'a', 'b', '2026-11-12T10:00:00.000000Z' FROM phil_review_vote WHERE campaign = 'cfp'");
        });

        self::assertSame(DbalVoteLog::MAX_ATTEMPTS, new DbalVoteLog($connection)->append('cfp', 'late', 'a', 'b', new DateTimeImmutable('2026-11-12 10:00:00 UTC'))->sequence);
    }

    public function testAnAssignmentKeptBetweenTheReadAndTheInsertIsKeptOnce(): void
    {
        [$connection, $racing] = $this->racingConnection();
        $racing->beforeTransactions(1, static function (DriverConnection $other): void {
            $other->exec("INSERT INTO phil_review_assignment VALUES ('cfp', 'a', 'racing')");
        });

        new DbalProposalStore($connection)->assign('cfp', 'a', 'racing');

        self::assertSame(['racing'], new DbalProposalStore($connection)->reviewersOf('cfp', 'a'));
    }

    public function testNumbersReadAsTextAreWhole(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP TABLE phil_review_vote');
        $connection->executeStatement('CREATE TABLE phil_review_vote (campaign TEXT, sequence, voter TEXT, winner TEXT, loser TEXT, cast_at TEXT)');
        $connection->insert('phil_review_vote', ['campaign' => 'cfp', 'sequence' => '41', 'voter' => 'v', 'winner' => 'a', 'loser' => 'b', 'cast_at' => '2026-11-12T10:00:00.000000Z']);

        self::assertSame(41, new DbalVoteLog($connection)->ofCampaign('cfp')[0]->sequence);
        self::assertSame(42, new DbalVoteLog($connection)->append('cfp', 'v', 'a', 'b', new DateTimeImmutable('2026-11-12 10:00:00 UTC'))->sequence);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function corruptRows(): iterable
    {
        $campaign = ['id' => 'c', 'definition' => '{"schedule":'.self::SCHEDULE.',"criteria":[{"id":"a","weight":1,"min":0,"max":5}],"policy":'.self::POLICY.'}', 'observed_phase' => 'review'];
        $review = ['campaign' => 'c', 'proposal' => 'p', 'reviewer' => 'r', 'scores' => '[]', 'submitted_at' => '2026-11-12T10:00:00.000000Z'];
        $decision = ['campaign' => 'c', 'proposal' => 'p', 'decision' => 'accepted', 'recorded_by' => 'm', 'recorded_at' => '2026-11-12T10:00:00.000000Z', 'applied_at' => null];
        $vote = ['campaign' => 'c', 'sequence' => 1, 'voter' => 'v', 'winner' => 'a', 'loser' => 'b', 'cast_at' => '2026-11-12T10:00:00.000000Z'];

        yield 'a phase unknown' => ['campaign', [...$campaign, 'observed_phase' => 'later'], 'The stored phase is unknown.'];
        yield 'a definition not JSON' => ['campaign', [...$campaign, 'definition' => '{'], 'The stored definition of a campaign is not JSON.'];
        yield 'a definition too deep' => ['campaign', [...$campaign, 'definition' => str_repeat('[', 9).str_repeat(']', 9)], 'The stored definition of a campaign is not JSON.'];
        yield 'a definition not an object' => ['campaign', [...$campaign, 'definition' => '"x"'], 'The stored definition of a campaign is not a list or an object.'];
        yield 'a definition not text' => ['campaign', [...$campaign, 'definition' => null], 'The stored definition of a campaign is not text.'];
        yield 'a schedule not a list' => ['campaign', [...$campaign, 'definition' => '{"schedule":{"a":1},"criteria":[],"policy":'.self::POLICY.'}'], 'The stored schedule of a campaign is not a list.'];
        yield 'an aggregation unknown' => ['campaign', [...$campaign, 'definition' => str_replace('"mean"', '"mode"', $campaign['definition'])], 'The stored aggregation of a policy is unknown.'];
        yield 'a visibility unknown' => ['campaign', [...$campaign, 'definition' => str_replace('"after-own-review"', '"sometimes"', $campaign['definition'])], 'The stored visibility of a policy is unknown.'];
        yield 'a weight not whole' => ['campaign', [...$campaign, 'definition' => str_replace('"weight":1', '"weight":1.5', $campaign['definition'])], 'The stored weight of a criterion is not a whole number.'];
        yield 'a blindness not true or false' => ['campaign', [...$campaign, 'definition' => str_replace('"blind":true', '"blind":1', $campaign['definition'])], 'The stored blindness of a policy is not true or false.'];
        yield 'a window instant not one' => ['campaign', [...$campaign, 'definition' => str_replace('2026-11-10T00:00:00.000000Z', '2026-11-10', $campaign['definition'])], 'The stored start of a phase is not an instant.'];
        yield 'a window instant out of range' => ['campaign', [...$campaign, 'definition' => str_replace('2026-11-10T00:00:00.000000Z', '2026-13-10T00:00:00.000000Z', $campaign['definition'])], 'The stored start of a phase is not an instant.'];
        yield 'a score not an object' => ['review', [...$review, 'scores' => '["a"]'], 'The stored score of a review is not a list or an object.'];
        yield 'a score value not whole' => ['review', [...$review, 'scores' => '[{"criterion":"a","value":"x"}]'], 'The stored value of a score is not a whole number.'];
        yield 'a score value with a new line' => ['review', [...$review, 'scores' => '[{"criterion":"a","value":"1\n"}]'], 'The stored value of a score is not a whole number.'];
        yield 'a review instant not text' => ['review', [...$review, 'submitted_at' => null], 'The stored instant of a review is not text.'];
        yield 'a decision unknown' => ['decision', [...$decision, 'decision' => 'maybe'], 'The stored decision is unknown.'];
        yield 'an application instant not one' => ['decision', [...$decision, 'applied_at' => 'tomorrow'], 'The stored instant a decision was applied is not an instant.'];
        yield 'a vote number not whole' => ['vote', [...$vote, 'sequence' => 'first'], 'The stored number of a vote is not a whole number.'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('corruptRows')]
    public function testARowOfTheWrongShapeIsRefused(string $table, array $row, string $message): void
    {
        $connection = $this->connection();
        $columns = array_keys($row);
        $connection->executeStatement('DROP TABLE phil_review_'.$table);
        $connection->executeStatement(\sprintf('CREATE TABLE phil_review_%s (%s)', $table, implode(', ', $columns)));
        $connection->insert('phil_review_'.$table, $row);

        $this->expectExceptionObject(new UnexpectedValueException($message));

        match ($table) {
            'campaign' => new DbalCampaignStore($connection)->get('c'),
            'review' => new DbalReviewStore($connection)->ofProposal('c', 'p'),
            'decision' => new DbalReviewStore($connection)->decisions('c'),
            default => new DbalVoteLog($connection)->ofCampaign('c'),
        };
    }

    public function testAConflictOfAnUnknownKindIsRefused(): void
    {
        $this->connection()->insert('phil_review_conflict', ['campaign' => 'c', 'reviewer' => 'r', 'kind' => 'friend', 'target' => 'a']);

        $this->expectExceptionObject(new UnexpectedValueException('The stored kind of a conflict is unknown.'));

        new DbalProposalStore($this->connection())->conflictsOf('c', 'r');
    }

    public function testAProposalWhoseAuthorsAreNotAListIsRefused(): void
    {
        $this->connection()->insert('phil_review_proposal', ['campaign' => 'c', 'id' => 'p', 'authors' => '{"a":"u"}']);

        $this->expectExceptionObject(new UnexpectedValueException('The stored authors of a proposal is not a list.'));

        new DbalProposalStore($this->connection())->get('c', 'p');
    }

    public function testAnAuthorNotTextIsRefused(): void
    {
        $this->connection()->insert('phil_review_proposal', ['campaign' => 'c', 'id' => 'p', 'authors' => '[5]']);

        $this->expectExceptionObject(new UnexpectedValueException('The stored author of a proposal is not text.'));

        new DbalProposalStore($this->connection())->ofCampaign('c');
    }

    /**
     * A database in memory with the stores' tables, on a connection where another request may write before each
     * transaction.
     *
     * @return array{Connection, RacingWriter}
     */
    private function racingConnection(): array
    {
        $racing = new RacingWriter();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$racing]));

        foreach ($this->schema()->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        return [$connection, $racing];
    }

    /**
     * @return list<string>
     */
    private function columns(Schema $schema, string $table): array
    {
        return array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $schema->getTable('phil_review_'.$table)->getColumns());
    }

    /**
     * @return list<string>
     */
    private function tableNames(Schema $schema): array
    {
        $names = array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables());
        sort($names);

        return $names;
    }
}
