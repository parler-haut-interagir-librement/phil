<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A product a supplier feeds this application, as its last message described it: what to sell it at, what a unit
 * costs, its tax category, how many are available, and whether it is still sold.
 *
 * **A message older than the one applied changes nothing**: messages may arrive out of order.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_product')]
final class SyndicatedProduct
{
    public Money $retail {
        get => Money::of($this->retailMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $cost {
        get => Money::of($this->costMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'name', length: 255)]
    private string $name;

    #[ORM\Column(name: 'retail', type: 'bigint')]
    private int $retailMinorUnits;

    #[ORM\Column(name: 'cost', type: 'bigint')]
    private int $costMinorUnits;

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    #[ORM\Column(name: 'tax_category', length: 64)]
    private string $taxCategory;

    #[ORM\Column(name: 'available', type: 'integer')]
    private int $available;

    #[ORM\Column(name: 'active')]
    private bool $active;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'supplier', length: 63)]
        public readonly string $supplier,
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        FeedItem $item,
        #[ORM\Column(name: 'described_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $describedAt,
    ) {
        $this->name = $item->name;
        $this->retailMinorUnits = $item->retail->minorUnits;
        $this->costMinorUnits = $item->cost->minorUnits;
        $this->currencyCode = $item->retail->currency->code;
        $this->taxCategory = $item->taxCategory;
        $this->available = $item->available;
        $this->active = $item->active;
    }

    public static function from(Partner $supplier, FeedItem $item, DateTimeImmutable $at): self
    {
        return new self($supplier->code, $item->sku, $item, $at);
    }

    /**
     * Applies a newer description; `false` when the message is older than the one applied.
     */
    public function describe(FeedItem $item, DateTimeImmutable $at): bool
    {
        if ($at <= $this->describedAt) {
            return false;
        }

        $this->name = $item->name;
        $this->retailMinorUnits = $item->retail->minorUnits;
        $this->costMinorUnits = $item->cost->minorUnits;
        $this->currencyCode = $item->retail->currency->code;
        $this->taxCategory = $item->taxCategory;
        $this->available = $item->available;
        $this->active = $item->active;
        $this->describedAt = $at;

        return true;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function taxCategory(): string
    {
        return $this->taxCategory;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function isSold(): bool
    {
        return $this->active && $this->available > 0;
    }

    public function describedAt(): DateTimeImmutable
    {
        return $this->describedAt;
    }
}
