<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A community copy of a product, claimed by someone who could not pay: one per person and product. The claimant is
 * the application's stable identifier of that person, never a name or an address.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_catalog_community_copy_claim')]
final class CommunityCopyClaim
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Id]
        #[ORM\Column(name: 'claimant', length: 64)]
        public readonly string $claimant,
        #[ORM\Column(name: 'claimed_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $claimedAt,
    ) {
    }

    public static function of(string $sku, string $claimant, DateTimeImmutable $at): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $claimant)) {
            throw InvalidValue::because(\sprintf('"%s" is not a claimant: 1 to 64 visible ASCII characters, a stable identifier.', $claimant));
        }

        return new self($sku, $claimant, $at);
    }
}
