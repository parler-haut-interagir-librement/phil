<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Poll\Ballot;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\Resource\ResourceReference;
use UnexpectedValueException;

/**
 * The ballots in the table `phil_poll_vote` and the participations in `phil_poll_participation`, through the entity
 * manager. A new ballot and its participation are written in one transaction, after looking for a ballot or a
 * participation of the voter already kept; the unique constraints refuse a second ballot of a voter that two requests
 * raced to cast, and the entity manager, closed by the failure, is reset by the kernel at the end of the request. A
 * ballot is saved by flushing the entity manager it was read from.
 *
 * The ballots of a count are read with a plain query, row by row, without hydrating an entity each. The ballots and
 * participations of a deleted poll are removed by two queries, and the ones the entity manager holds are detached.
 */
final readonly class DoctrineVoteRepository implements VoteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function ofVoter(string $poll, ResourceReference $voter): ?Vote
    {
        return $this->entityManager->getRepository(Vote::class)->findOneBy(['poll' => $poll, 'voter' => $voter->toString()]);
    }

    #[Override]
    public function withReceipt(string $poll, string $receiptHash): ?Vote
    {
        return $this->entityManager->getRepository(Vote::class)->findOneBy(['poll' => $poll, 'receipt' => $receiptHash]);
    }

    #[Override]
    public function hasParticipated(string $poll, ResourceReference $voter): bool
    {
        return null !== $this->entityManager->find(Participation::class, ['poll' => $poll, 'voter' => $voter->toString()]);
    }

    #[Override]
    public function add(Vote $vote, ?Participation $participation = null): void
    {
        $voter = $vote->voter();

        if (($voter instanceof ResourceReference && $this->ofVoter($vote->poll, $voter) instanceof Vote)
            || ($participation instanceof Participation && $this->hasParticipated($participation->poll, $participation->voter()))) {
            throw AlreadyVoted::in($vote->poll);
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($vote, $participation): void {
                if ($participation instanceof Participation) {
                    $this->entityManager->persist($participation);
                }

                $this->entityManager->persist($vote);
            });
        } catch (UniqueConstraintViolationException) {
            throw AlreadyVoted::in($vote->poll);
        }
    }

    #[Override]
    public function save(Vote $vote): void
    {
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Vote $vote): void
    {
        $this->entityManager->remove($vote);
        $this->entityManager->flush();
    }

    #[Override]
    public function ballots(string $poll): iterable
    {
        $rows = $this->entityManager->getConnection()->executeQuery('SELECT choices FROM '.Vote::TABLE.' WHERE poll = ?', [$poll]);

        foreach ($rows->iterateColumn() as $choices) {
            yield Ballot::of($this->choices($choices));
        }
    }

    #[Override]
    public function count(string $poll): int
    {
        return $this->entityManager->getRepository(Vote::class)->count(['poll' => $poll]);
    }

    #[Override]
    public function removeAllOf(string $poll): void
    {
        $this->entityManager->wrapInTransaction(function () use ($poll): void {
            $this->entityManager->createQuery('DELETE FROM '.Vote::class.' v WHERE v.poll = :poll')->setParameter('poll', $poll)->execute();
            $this->entityManager->createQuery('DELETE FROM '.Participation::class.' p WHERE p.poll = :poll')->setParameter('poll', $poll)->execute();
        });

        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (($entity instanceof Vote || $entity instanceof Participation) && $entity->poll === $poll) {
                    $this->entityManager->detach($entity);
                }
            }
        }
    }

    /**
     * The choices of a row, as the column keeps them: a JSON list of option identifiers.
     *
     * @return list<string>
     *
     * @throws UnexpectedValueException when the row holds anything else
     */
    private function choices(mixed $column): array
    {
        $choices = \is_string($column) ? json_decode($column, true) : null;

        if (!\is_array($choices) || !array_is_list($choices) || array_any($choices, static fn (mixed $choice): bool => !\is_string($choice))) {
            throw new UnexpectedValueException('A ballot of the poll is not kept as a list of options.');
        }

        /** @var list<string> $choices */
        return $choices;
    }
}
