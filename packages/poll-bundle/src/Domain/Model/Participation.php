<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;

/**
 * That a voter cast a ballot in an anonymous poll, kept apart from the ballot: it stops a second ballot without
 * telling which ballot was theirs. It has no time, so that it cannot be matched to a ballot by the instant.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Participation
{
    public const string TABLE = 'phil_poll_participation';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'poll', length: 36)]
        public readonly string $poll,
        #[ORM\Id]
        #[ORM\Column(name: 'voter', length: 320)]
        private readonly string $voter,
    ) {
    }

    public static function of(string $poll, ResourceReference $voter): self
    {
        return new self($poll, $voter->toString());
    }

    public function voter(): ResourceReference
    {
        return ResourceReference::fromString($this->voter);
    }
}
