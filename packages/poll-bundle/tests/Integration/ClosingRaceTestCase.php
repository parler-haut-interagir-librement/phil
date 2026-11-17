<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\Exception\VoteRefused;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * A ballot and a closing on two connections, interleaved: the voter's request reads the poll open, the manager's
 * request closes it and commits, then the voter's request writes. Each ballot — cast, changed, changed by receipt,
 * withdrawn — is refused as the poll not open, and the ballots kept are those before the closing.
 *
 * The closing runs while the policy is asked about the voter, which comes after the poll was read and before the
 * ballot is written.
 */
abstract class ClosingRaceTestCase extends TestCase
{
    private const array OPTIONS = ['a' => 'A', 'b' => 'B'];

    /** @var list<Connection> */
    private array $connections = [];

    abstract protected function connect(): Connection;

    public function testABallotCastAfterTheClosingIsRefused(): void
    {
        [$voter, $manager, $policy, $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(false), self::boss());
        $policy->whileVoting = static fn () => $manager->close($poll->id, self::boss());

        $this->assertRefused(static fn () => $voter->cast($poll->id, self::bob(), ['a']));

        self::assertSame(0, $managerViews()->turnout($poll->id));
        self::assertNull($managerViews()->ballotOf($poll->id, self::bob()));
    }

    public function testAnAnonymousBallotCastAfterTheClosingIsRefusedWithItsParticipation(): void
    {
        [$voter, $manager, $policy, $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(true), self::boss());
        $policy->whileVoting = static fn () => $manager->close($poll->id, self::boss());

        $this->assertRefused(static fn () => $voter->cast($poll->id, self::bob(), ['a']));

        self::assertSame(0, $managerViews()->turnout($poll->id));
        self::assertFalse($managerViews()->hasVoted($poll->id, self::bob()));
    }

    public function testABallotChangedAfterTheClosingIsKeptAsItWas(): void
    {
        [$voter, $manager, $policy, $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(false), self::boss());
        $voter->cast($poll->id, self::bob(), ['a']);
        $policy->whileVoting = static fn () => $manager->close($poll->id, self::boss());

        $this->assertRefused(static fn () => $voter->change($poll->id, self::bob(), ['b']));

        self::assertSame(['a'], $managerViews()->ballotOf($poll->id, self::bob())?->choices);
    }

    public function testAnAnonymousBallotChangedAfterTheClosingIsKeptAsItWas(): void
    {
        [$voter, $manager, $policy, $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(true), self::boss());
        $receipt = (string) $voter->cast($poll->id, self::bob(), ['a'])->receipt;
        $policy->whileVoting = static fn () => $manager->close($poll->id, self::boss());

        $this->assertRefused(static fn () => $voter->changeWithReceipt($poll->id, self::bob(), $receipt, ['b']));

        self::assertSame(['a'], $managerViews()->ballotOfReceipt($poll->id, $receipt)?->choices);
    }

    public function testABallotWithdrawnAfterTheClosingIsKept(): void
    {
        [$voter, $manager, $policy, $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(false), self::boss());
        $voter->cast($poll->id, self::bob(), ['a']);
        $policy->whileVoting = static fn () => $manager->close($poll->id, self::boss());

        $this->assertRefused(static fn () => $voter->withdraw($poll->id, self::bob()));

        self::assertSame(1, $managerViews()->turnout($poll->id));
        self::assertSame(['a'], $managerViews()->ballotOf($poll->id, self::bob())?->choices);
    }

    public function testABallotCastBeforeTheClosingIsKept(): void
    {
        [$voter, $manager, , $managerViews] = $this->requests();
        $poll = $manager->create($this->draft(false), self::boss());
        $voter->cast($poll->id, self::bob(), ['a']);
        $manager->close($poll->id, self::boss());

        self::assertSame(1, $managerViews()->turnout($poll->id));
        self::assertTrue($managerViews()->get($poll->id)->isClosedAt(new DateTimeImmutable('2026-11-08 10:00:00 UTC')));
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];
        $this->cleanUp();
    }

    /**
     * Removes what the database of the subclass leaves behind, once its connections are closed.
     */
    protected function cleanUp(): void
    {
    }

    /**
     * The voter's request and the manager's request, each on its own connection, the voter's policy, and the
     * manager's polls read afresh.
     *
     * @return array{Polls, Polls, Policy, callable(): Polls}
     */
    private function requests(): array
    {
        $voterConnection = $this->connections[] = $this->connect();
        $managerConnection = $this->connections[] = $this->connect();
        $voterEntityManager = $this->entityManager($voterConnection);
        $managerEntityManager = $this->entityManager($managerConnection);

        $tool = new SchemaTool($voterEntityManager);
        $classes = [$voterEntityManager->getClassMetadata(Poll::class), $voterEntityManager->getClassMetadata(Vote::class), $voterEntityManager->getClassMetadata(Participation::class)];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        $clock = new MockClock('2026-11-08 10:00:00 UTC');
        $policy = new Policy();
        $voter = new Polls(new DoctrinePollRepository($voterEntityManager), new DoctrineVoteRepository($voterEntityManager), $clock, $policy, CountingMethods::defaults());
        $manager = new Polls(new DoctrinePollRepository($managerEntityManager), new DoctrineVoteRepository($managerEntityManager), $clock, new Policy(), CountingMethods::defaults());

        return [$voter, $manager, $policy, static function () use ($managerEntityManager, $manager): Polls {
            $managerEntityManager->clear();

            return $manager;
        }];
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager($connection, $config);
    }

    /**
     * @param callable(): mixed $ballot
     */
    private function assertRefused(callable $ballot): void
    {
        try {
            $ballot();
            self::fail('The ballot was kept after the closing.');
        } catch (VoteRefused $voteRefused) {
            self::assertSame('This poll is not open.', $voteRefused->getMessage());
        }
    }

    private function draft(bool $anonymous): PollDraft
    {
        $options = [];

        foreach (self::OPTIONS as $id => $label) {
            $options[] = PollOption::of($id, $label);
        }

        return new PollDraft('Q', $options, BallotKind::Single, anonymous: $anonymous);
    }

    private static function boss(): ResourceReference
    {
        return ResourceReference::of('user', 'boss');
    }

    private static function bob(): ResourceReference
    {
        return ResourceReference::of('user', 'bob');
    }
}
