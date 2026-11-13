<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Poll\Ballot;
use Phil\Resource\ResourceReference;

/**
 * One ballot cast in a poll, its choices as the voter gave them.
 *
 * A named ballot carries its voter and the instant it was cast or last changed; a voter has at most one per poll. An
 * anonymous ballot carries neither: only the hash of the receipt given to its voter, by which they may change it, and
 * nothing that ties it to them — its identifier is random and it has no time.
 *
 * The unique constraint on the poll and the voter also serves as the index of the ballots of a poll.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_poll_vote_voter', columns: ['poll', 'voter'])]
#[ORM\UniqueConstraint(name: 'phil_poll_vote_receipt', columns: ['receipt'])]
final class Vote
{
    public const string TABLE = 'phil_poll_vote';

    /**
     * @param list<string> $choices
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'poll', length: 36)]
        public readonly string $poll,
        #[ORM\Column(name: 'voter', length: 320, nullable: true)]
        private readonly ?string $voter,
        #[ORM\Column(name: 'receipt', length: 64, nullable: true)]
        private readonly ?string $receipt,
        #[ORM\Column(name: 'choices', type: 'json')]
        private array $choices,
        #[ORM\Column(name: 'cast_at', nullable: true)]
        private ?DateTimeImmutable $castAt,
    ) {
    }

    /**
     * The ballot of a known voter.
     */
    public static function named(string $id, string $poll, ResourceReference $voter, Ballot $ballot, DateTimeImmutable $now): self
    {
        return new self($id, $poll, $voter->toString(), null, $ballot->choices, self::second($now));
    }

    /**
     * A ballot kept apart from its voter, found again by the hash of its receipt.
     */
    public static function anonymous(string $id, string $poll, string $receiptHash, Ballot $ballot): self
    {
        return new self($id, $poll, null, $receiptHash, $ballot->choices, null);
    }

    public function ballot(): Ballot
    {
        return Ballot::of($this->choices);
    }

    public function voter(): ?ResourceReference
    {
        return null === $this->voter ? null : ResourceReference::fromString($this->voter);
    }

    public function isAnonymous(): bool
    {
        return null === $this->voter;
    }

    public function receiptHash(): ?string
    {
        return $this->receipt;
    }

    /**
     * When a named ballot was cast or last changed; `null` for an anonymous one.
     */
    public function castAt(): ?DateTimeImmutable
    {
        return $this->castAt;
    }

    /**
     * Replaces the choices; a named ballot takes the instant of the change.
     */
    public function change(Ballot $ballot, DateTimeImmutable $now): void
    {
        $this->choices = $ballot->choices;

        if (null !== $this->voter) {
            $this->castAt = self::second($now);
        }
    }

    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$moment->getTimestamp());
    }
}
