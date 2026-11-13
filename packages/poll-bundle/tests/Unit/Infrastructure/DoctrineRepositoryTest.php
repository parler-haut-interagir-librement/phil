<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Poll\Ballot;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use UnexpectedValueException;

/**
 * The stores on SQLite in memory, their tables created from the mapping as an application's schema tools create them,
 * and each entity read back from the database, the entity manager cleared.
 */
#[CoversClass(DoctrinePollRepository::class)]
#[CoversClass(DoctrineVoteRepository::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    #[Override]
    protected function stores(): array
    {
        $this->entityManager = $this->entityManager();

        return [new DoctrinePollRepository($this->entityManager), new DoctrineVoteRepository($this->entityManager)];
    }

    #[Override]
    protected function reread(PollRepository $polls, string $id): Poll
    {
        $this->entityManager?->clear();

        return parent::reread($polls, $id);
    }

    #[Override]
    protected function rereadVote(VoteRepository $votes, string $poll, ResourceReference $voter): Vote
    {
        $this->entityManager?->clear();

        return parent::rereadVote($votes, $poll, $voter);
    }

    public function testABallotKeptAsAnythingButAListOfOptionsIsUnreadable(): void
    {
        foreach (['{"a":"b"}', '[1]', '"a"', '[[["a"]]]', 'null'] as $choices) {
            $entityManager = $this->entityManager();
            $entityManager->getConnection()->insert(Vote::TABLE, ['id' => 'v1', 'poll' => 'p1', 'choices' => $choices]);

            try {
                iterator_to_array(new DoctrineVoteRepository($entityManager)->ballots('p1'));
                self::fail($choices);
            } catch (UnexpectedValueException $unexpectedValueException) {
                self::assertSame('A ballot of the poll is not kept as a list of options.', $unexpectedValueException->getMessage());
            }
        }
    }

    public function testTheEntitiesOfADeletedPollAreDetached(): void
    {
        $entityManager = $this->entityManager();
        $polls = new DoctrinePollRepository($entityManager);
        $votes = new DoctrineVoteRepository($entityManager);
        $poll = Poll::create('p1', 'Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], PollRules::single(['a', 'b']), 'choices', true, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
        $polls->save($poll);
        $deleted = Vote::anonymous('v1', 'p1', 'h1', Ballot::of(['a']));
        $participation = Participation::of('p1', ResourceReference::of('user', 'ada'));
        $kept = Vote::anonymous('v2', 'p2', 'h2', Ballot::of(['a']));
        $keptParticipation = Participation::of('p2', ResourceReference::of('user', 'ada'));
        $votes->add($deleted, $participation);
        $votes->add($kept, $keptParticipation);

        $votes->removeAllOf('p1');

        self::assertSame([false, false, true, true, true], [$entityManager->contains($deleted), $entityManager->contains($participation), $entityManager->contains($kept), $entityManager->contains($keptParticipation), $entityManager->contains($poll)]);
        self::assertFalse($votes->hasParticipated('p1', ResourceReference::of('user', 'ada')));
    }

    private function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Poll::class), $entityManager->getClassMetadata(Vote::class), $entityManager->getClassMetadata(Participation::class)]);

        return $entityManager;
    }
}
