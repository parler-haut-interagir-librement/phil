<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Dbal\DbalCampaignStore;
use Phil\ProposalReview\Dbal\DbalProposalStore;
use Phil\ProposalReview\Dbal\DbalReviewStore;
use Phil\ProposalReview\Dbal\DbalVoteLog;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Pairwise\Vote;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Score;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use Phil\ProposalReview\Tests\Unit\Fixture\RecordingEvents;
use Phil\ProposalReview\Visibility;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The contract of the four stores on the database of a URL, their tables dropped and created again from their own
 * schema before each test.
 */
abstract class DbalStoreTestCase extends TestCase
{
    private const array TABLES = ['campaign', 'proposal', 'assignment', 'conflict', 'review', 'decision', 'vote'];

    protected ?Connection $connection = null;

    /**
     * The URL of the database, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();

        if (null === $url) {
            self::markTestSkipped('Set the URL of the server to run this test against it.');
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url));
        $schemaManager = $this->connection->createSchemaManager();

        foreach (self::TABLES as $table) {
            if ($schemaManager->tablesExist(['phil_review_'.$table])) {
                $this->connection->executeStatement('DROP TABLE phil_review_'.$table);
            }
        }

        foreach ($this->schema()->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testACampaignComesBackAsItWasSaved(): void
    {
        $store = new DbalCampaignStore($this->connection());
        $campaign = new Campaign(
            '7',
            Jury::schedule(),
            [new Criterion('content', 3, 1, 5), new Criterion('clarity', 1, -2, 10)],
            new ReviewPolicy(false, 2, 4, Aggregation::Median, Visibility::Always, false),
            Phase::Review,
        );
        $store->save($campaign);

        self::assertEquals($campaign, $store->get('7'));
        self::assertSame('7', $store->get('7')?->id);
        self::assertNull($store->get('8'));
        self::assertNull($store->get('7 '));

        $open = new Campaign('open', Jury::schedule(), [new Criterion('a')]);
        $store->save($open);
        $store->save($open->withObservedPhase(Phase::Deliberation));

        self::assertSame(Phase::Deliberation, $store->get('open')?->observedPhase);
        self::assertNull($store->get('open')->policy->maxReviews);
        self::assertEquals($campaign, $store->get('7'));
    }

    public function testCampaignIdentifiersAreComparedByteForByte(): void
    {
        $store = new DbalCampaignStore($this->connection());
        $store->save(new Campaign('CFP', Jury::schedule(), [new Criterion('a')]));
        $store->save(new Campaign('café', Jury::schedule(), [new Criterion('a')]));

        self::assertNull($store->get('cfp'));
        self::assertNull($store->get('cafe'));
        self::assertSame('CFP', $store->get('CFP')?->id);
        self::assertSame('café', $store->get('café')?->id);
    }

    public function testProposalsAreKeptReplacedAndListedInIdentifierOrder(): void
    {
        $store = new DbalProposalStore($this->connection());
        $store->save(new Proposal('cfp', 'b', []));
        $store->save(new Proposal('cfp', 'a', ['u']));
        $store->save(new Proposal('cfp', '10', []));
        $store->save(new Proposal('cfp', 'B', []));
        $store->save(new Proposal('other', 'c', []));
        $store->save(new Proposal('cfp', 'a', ['v', 'w']));

        self::assertSame(['10', 'B', 'a', 'b'], array_map(static fn (Proposal $proposal): string => $proposal->id, $store->ofCampaign('cfp')));
        self::assertEquals(new Proposal('cfp', 'a', ['v', 'w']), $store->get('cfp', 'a'));
        self::assertSame('10', $store->get('cfp', '10')?->id);
        self::assertNull($store->get('cfp', 'c'));
        self::assertNull($store->get('cfp', 'a '));
        self::assertNull($store->get('cfp ', 'a'));
        self::assertSame([], $store->ofCampaign('none'));
        self::assertSame([], $store->ofCampaign('cfp '));
    }

    public function testAssignmentsAreKeptOnceAndCountedPerReviewer(): void
    {
        $store = new DbalProposalStore($this->connection());
        $store->assign('cfp', 'a', 'r2');
        $store->assign('cfp', 'a', 'r1');
        $store->assign('cfp', 'a', 'r1');
        $store->assign('cfp', 'b', 'r1');
        $store->assign('cfp', 'b', '7');
        $store->assign('cfp', 'b', 'R1');
        $store->assign('other', 'c', 'r1');

        self::assertSame(['r1', 'r2'], $store->reviewersOf('cfp', 'a'));
        self::assertSame(['7', 'R1', 'r1'], $store->reviewersOf('cfp', 'b'));
        self::assertSame([], $store->reviewersOf('cfp', '10'));
        self::assertSame([], $store->reviewersOf('cfp', 'a '));
        self::assertSame([], $store->reviewersOf('cfp ', 'a'));
        self::assertSame([7 => 1, 'R1' => 1, 'r1' => 2, 'r2' => 1], $store->assignmentCounts('cfp'));
        self::assertSame([], $store->assignmentCounts('none'));
        self::assertSame([], $store->assignmentCounts('cfp '));
    }

    public function testConflictsAreKeptOncePerReviewer(): void
    {
        $store = new DbalProposalStore($this->connection());
        $store->declareConflict(ConflictOfInterest::withAuthor('cfp', 'r1', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r1', 'b'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r1', 'b'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r1', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r2', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('other', 'r1', 'a'));

        self::assertEquals([
            ConflictOfInterest::withProposal('cfp', 'r1', 'a'),
            ConflictOfInterest::withProposal('cfp', 'r1', 'b'),
            ConflictOfInterest::withAuthor('cfp', 'r1', 'a'),
        ], $store->conflictsOf('cfp', 'r1'));
        self::assertEquals([ConflictOfInterest::withProposal('other', 'r1', 'a')], $store->conflictsOf('other', 'r1'));
        self::assertSame([], $store->conflictsOf('cfp', 'r3'));
        self::assertSame([], $store->conflictsOf('cfp', 'r1 '));
        self::assertSame([], $store->conflictsOf('cfp ', 'r1'));
    }

    public function testAnAssignmentInsideATransactionIsKeptOnce(): void
    {
        $store = new DbalProposalStore($this->connection());

        $this->connection()->transactional(static function () use ($store): void {
            $store->assign('cfp', 'a', 'r1');
            $store->assign('cfp', 'a', 'r1');
        });

        self::assertSame(['r1'], $store->reviewersOf('cfp', 'a'));
    }

    public function testReviewsAreKeptReplacedAndListedInReviewerOrder(): void
    {
        $store = new DbalReviewStore($this->connection());
        $at = new DateTimeImmutable('2026-11-12 10:00:00.123456 UTC');
        $store->save(new Review('cfp', 'a', 'r2', [new Score('content', 4)], $at));
        $store->save(new Review('cfp', 'a', 'r1', [], $at));
        $store->save(new Review('cfp', 'a', 'R3', [], $at));

        $again = new Review('cfp', 'a', 'r2', [new Score('content', 2), new Score('clarity', -1)], new DateTimeImmutable('2026-11-12 06:00:00 America/Toronto'));
        $store->save($again);
        $store->save(new Review('cfp', 'b', 'r1', [], $at));
        $store->save(new Review('other', 'a', 'r1', [], $at));

        $reviews = $store->ofProposal('cfp', 'a');
        self::assertSame(['R3', 'r1', 'r2'], array_map(static fn (Review $review): string => $review->reviewer, $reviews));
        self::assertEquals($again, $reviews[2]);
        self::assertSame('2026-11-12T11:00:00.000000Z', $reviews[2]->submittedAt->format('Y-m-d\TH:i:s.u\Z'));
        self::assertEquals($at, $reviews[1]->submittedAt);
        self::assertSame([], $store->ofProposal('cfp', 'z'));
        self::assertSame([], $store->ofProposal('cfp', 'a '));
        self::assertSame([], $store->ofProposal('cfp ', 'a'));
    }

    public function testDecisionsAreKeptReplacedAndListedInProposalOrder(): void
    {
        $store = new DbalReviewStore($this->connection());
        $at = new DateTimeImmutable('2026-11-24 09:30:00 UTC');
        $store->saveDecision(new DecisionRecord('cfp', 'b', Decision::Waitlisted, 'chair', $at));
        $store->saveDecision(new DecisionRecord('cfp', 'a', Decision::Accepted, 'chair', $at));

        $applied = new DecisionRecord('cfp', 'a', Decision::Rejected, 'chair-2', $at, $at->modify('+1 day'));
        $store->saveDecision($applied);
        $store->saveDecision(new DecisionRecord('other', 'c', Decision::Accepted, 'chair', $at));

        $decisions = $store->decisions('cfp');
        self::assertSame([['a', Decision::Rejected], ['b', Decision::Waitlisted]], array_map(static fn (DecisionRecord $record): array => [$record->proposal, $record->decision], $decisions));
        self::assertEquals($applied, $decisions[0]);
        self::assertFalse($decisions[0]->isPending());
        self::assertTrue($decisions[1]->isPending());
        self::assertSame([], $store->decisions('none'));
        self::assertSame([], $store->decisions('cfp '));
    }

    public function testVotesAreNumberedFromOnePerCampaignAndReadInOrder(): void
    {
        $log = new DbalVoteLog($this->connection());
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');

        $first = $log->append('cfp', 'v', 'a', 'b', $at);
        self::assertSame(1, $first->sequence);
        self::assertSame(2, $log->append('cfp', 'w', 'b', 'a', $at->modify('+1 second'))->sequence);
        self::assertSame(1, $log->append('other', 'v', 'a', 'b', $at)->sequence);

        for ($i = 3; $i <= 11; ++$i) {
            self::assertSame($i, $log->append('cfp', 'v', 'a', 'b', $at)->sequence);
        }

        $votes = $log->ofCampaign('cfp');
        self::assertSame(range(1, 11), array_map(static fn (Vote $vote): int => $vote->sequence, $votes));
        self::assertEquals($first, $votes[0]);
        self::assertSame(['w', 'b', 'a'], [$votes[1]->voter, $votes[1]->winner, $votes[1]->loser]);
        self::assertSame([], $log->ofCampaign('none'));
        self::assertSame([], $log->ofCampaign('cfp '));
    }

    public function testTheBoardRunsACampaignOnTheStores(): void
    {
        $clock = new MockClock('2026-11-05 12:00:00 UTC');
        $campaigns = new DbalCampaignStore($this->connection());
        $proposals = new DbalProposalStore($this->connection());
        $reviews = new DbalReviewStore($this->connection());
        $campaigns->save(new Campaign('cfp', Jury::schedule(), [new Criterion('content', 2, 1, 5)], new ReviewPolicy(minReviews: 1)));
        $board = new ReviewBoard($campaigns, $proposals, $reviews, $clock, new RecordingEvents());

        $board->submit('cfp', 'talk', ['author']);
        $board->assign('cfp', 'talk', 'reviewer');

        $clock->modify('2026-11-11 12:00:00 UTC');
        $board->review('cfp', 'talk', 'reviewer', [new Score('content', 5)]);
        $clock->modify('2026-11-24 12:00:00 UTC');
        $board->decide('cfp', 'talk', Decision::Accepted, 'chair');
        $board->applyDecisions('cfp');
        $board->advance('cfp');

        self::assertSame(Phase::Deliberation, $campaigns->get('cfp')?->observedPhase);
        self::assertSame(1.0, $board->standings('cfp')[0]->score);
        self::assertFalse($reviews->decisions('cfp')[0]->isPending());
    }

    /**
     * The four stores' tables, from their own schema.
     */
    protected function schema(?string $prefix = null): Schema
    {
        $connection = $this->connection();
        $stores = null === $prefix
            ? [new DbalCampaignStore($connection), new DbalProposalStore($connection), new DbalReviewStore($connection), new DbalVoteLog($connection)]
            : [new DbalCampaignStore($connection, $prefix), new DbalProposalStore($connection, $prefix), new DbalReviewStore($connection, $prefix), new DbalVoteLog($connection, $prefix)];
        $schema = Schema::editor()->create();

        foreach ($stores as $store) {
            $schema = $store->configureSchema($schema);
        }

        return $schema;
    }

    protected function connection(): Connection
    {
        $this->assertInstanceOf(Connection::class, $this->connection);

        return $this->connection;
    }
}
