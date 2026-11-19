<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * The id of a message already applied, per partner: a delivery repeated by its sender is recognised and applied no
 * second time, and an id another partner happens to use is not mistaken for it. Kept until the retention purges it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_received')]
#[ORM\Index(columns: ['received_at'])]
final class ReceivedMessage
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 255)]
        public readonly string $id,
        #[ORM\Column(name: 'type', length: 64)]
        public readonly string $type,
        #[ORM\Column(name: 'received_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $receivedAt,
    ) {
    }

    public static function of(string $id, Partner $partner, string $type, DateTimeImmutable $at): self
    {
        return new self($partner->code, $id, mb_substr($type, 0, 64), $at);
    }
}
