<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A price the supplier published to a reseller for one SKU: the retail price and what a unit costs that reseller,
 * from the moment it was published until the next one. The supplier checks the cost of each line a reseller forwards
 * against these, rather than take the reseller's word for what it owes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_published_price')]
#[ORM\Index(columns: ['reseller', 'sku', 'currency', 'published_at'])]
final class PublishedPrice
{
    public Money $retail {
        get => Money::of($this->retailMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $cost {
        get => Money::of($this->costMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'reseller', length: 63)]
        public readonly string $reseller,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'retail', type: 'bigint')]
        private readonly int $retailMinorUnits,
        #[ORM\Column(name: 'cost', type: 'bigint')]
        private readonly int $costMinorUnits,
        #[ORM\Column(name: 'published_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $publishedAt,
    ) {
    }

    public static function of(Partner $reseller, string $sku, Money $retail, Money $cost, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $reseller->code, $sku, $retail->currency->code, $retail->minorUnits, $cost->minorUnits, $at);
    }

    public function samePrice(Money $retail, Money $cost): bool
    {
        return $retail->currency->code === $this->currencyCode && $retail->minorUnits === $this->retailMinorUnits && $cost->minorUnits === $this->costMinorUnits;
    }
}
