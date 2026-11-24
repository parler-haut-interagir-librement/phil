<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A vote token already used for one question, kept until the token expires so that it cannot vote twice. Its
 * identifier is the hash of the token's random part and the question: nothing in it points to a person.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_faq_spent_vote_expiry', columns: ['expires_at'])]
final class SpentVoteToken
{
    public const string TABLE = 'phil_faq_spent_vote';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'expires_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
