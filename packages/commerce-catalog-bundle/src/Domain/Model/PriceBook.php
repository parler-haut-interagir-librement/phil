<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A list of unit prices other than the base prices, for whom and when it applies: a customer group, a channel — a
 * site, a point of sale —, a period, and quantity tiers per SKU.
 *
 * A condition left empty applies to everyone, everywhere, always. The books are tried by code, each one moved after
 * the books its `after` list names, so that their prices win over its own: among the books that apply, the first
 * that prices the SKU for the line's quantity wins; within a book, the tier of the highest quantity reached.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_catalog_price_book')]
#[ORM\Index(name: self::ACTIVE_INDEX, columns: ['active'])]
final class PriceBook
{
    /** The index the active books are read on. */
    public const string ACTIVE_INDEX = 'phil_commerce_catalog_price_book_active';

    /** The books an `after` list names, at most. */
    public const int MAX_AFTER = 1_000;

    private const string CODE = '/^[a-z0-9][a-z0-9_.-]{0,63}$/D';

    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /** @var list<array{sku: string, min_quantity: int, unit_price: int}> sorted by SKU in byte order, then quantity */
    #[ORM\Column(name: 'tiers', type: 'json')]
    private array $tiers = [];

    /** @var list<string> the books tried before this one, whose prices win over its own */
    #[ORM\Column(name: 'after_books', type: 'json')]
    private array $after = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'customer_group', length: 64, nullable: true)]
        public readonly ?string $customerGroup,
        #[ORM\Column(name: 'channel', length: 64, nullable: true)]
        public readonly ?string $channel,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable', nullable: true)]
        public readonly ?DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable', nullable: true)]
        public readonly ?DateTimeImmutable $endsAt,
    ) {
    }

    /**
     * @param DateTimeImmutable|null $endsAt excluded: the book no longer applies from that moment
     * @param list<string>           $after  the books tried before this one, whose prices win over its own
     */
    public static function create(string $code, string $name, Currency $currency, ?string $customerGroup = null, ?string $channel = null, ?DateTimeImmutable $startsAt = null, ?DateTimeImmutable $endsAt = null, array $after = []): self
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a price book code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($name)) {
            throw InvalidValue::because(\sprintf('The price book %s has a name.', $code));
        }

        if ('' === $customerGroup || '' === $channel) {
            throw InvalidValue::because(\sprintf('A customer group or a channel of the price book %s, when given, has a name.', $code));
        }

        if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable && $endsAt <= $startsAt) {
            throw InvalidValue::because(\sprintf('The price book %s ends after it starts.', $code));
        }

        $book = new self($code, $name, $currency->code, $customerGroup, $channel, $startsAt, $endsAt);
        $book->placeAfter($after);

        return $book;
    }

    /**
     * @return list<string> the books tried before this one, whose prices win over its own
     */
    public function after(): array
    {
        return $this->after;
    }

    /**
     * Names the books tried before this one: their prices win over its own wherever both apply. A name that is no
     * active book is passed over when the books are ordered; none leaves the book in its place by code.
     *
     * @param list<string> $after
     */
    public function placeAfter(array $after): void
    {
        $after = array_values(array_unique($after));

        if (\count($after) > self::MAX_AFTER) {
            throw InvalidValue::because(\sprintf('The price book %s is placed after %d books at most.', $this->code, self::MAX_AFTER));
        }

        foreach ($after as $code) {
            if (1 !== preg_match(self::CODE, $code)) {
                throw InvalidValue::because(\sprintf('"%s" is not a price book code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
            }

            if ($code === $this->code) {
                throw InvalidValue::because(\sprintf('The price book %s is never placed after itself.', $code));
            }
        }

        $this->after = $after;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }

    /**
     * Whether the book applies to a sale to a buyer of this group, through this channel, at this moment.
     */
    public function appliesTo(?string $customerGroup, ?string $channel, DateTimeImmutable $at): bool
    {
        return $this->active
            && (null === $this->customerGroup || $this->customerGroup === $customerGroup)
            && (null === $this->channel || $this->channel === $channel)
            && (!$this->startsAt instanceof DateTimeImmutable || $at >= $this->startsAt)
            && (!$this->endsAt instanceof DateTimeImmutable || $at < $this->endsAt);
    }

    /**
     * Sets the unit price of a SKU from a quantity, replacing the one of the same SKU and quantity.
     */
    public function setPrice(PriceTier $tier): void
    {
        if ($tier->unitPrice->currency->code !== $this->currencyCode) {
            throw CatalogRefused::currency(\sprintf('The price book %s', $this->code), $this->currencyCode, $tier->unitPrice->currency->code);
        }

        $row = ['sku' => $tier->sku, 'min_quantity' => $tier->minQuantity, 'unit_price' => $tier->unitPrice->minorUnits];
        // The tiers are kept sorted: a binary search finds the first tier not before the new one — the one it replaces,
        // or where it goes —, so that importing a book of n tiers costs n searches rather than n sorts.
        $low = 0;
        $high = \count($this->tiers);

        while ($low < $high) {
            $middle = ($low + $high) >> 1;
            $current = $this->tiers[$middle];

            if (($current['sku'] === $tier->sku ? $current['min_quantity'] <=> $tier->minQuantity : strcmp($current['sku'], $tier->sku)) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        if (($this->tiers[$low]['sku'] ?? null) === $tier->sku && $this->tiers[$low]['min_quantity'] === $tier->minQuantity) {
            $this->tiers[$low] = $row;

            return;
        }

        array_splice($this->tiers, $low, 0, [$row]);
    }

    public function removePrice(string $sku, int $minQuantity): void
    {
        $kept = [];

        foreach ($this->tiers as $tier) {
            if ($tier['sku'] !== $sku || $tier['min_quantity'] !== $minQuantity) {
                $kept[] = $tier;
            }
        }

        $this->tiers = $kept;
    }

    /**
     * @return list<PriceTier> by SKU in byte order, then quantity
     */
    public function tiers(): array
    {
        $tiers = [];
        $currency = $this->currency;

        foreach ($this->tiers as $tier) {
            $tiers[] = PriceTier::of($tier['sku'], $tier['min_quantity'], Money::of($tier['unit_price'], $currency));
        }

        return $tiers;
    }

    /**
     * The unit price of the tier of the highest quantity the line reaches, or `null` when the book does not price the
     * SKU for that quantity.
     */
    public function priceFor(string $sku, int $quantity): ?Money
    {
        $price = null;

        foreach ($this->tiers as $tier) {
            if ($tier['sku'] === $sku && $tier['min_quantity'] <= $quantity) {
                $price = $tier['unit_price'];
            }
        }

        return null === $price ? null : Money::of($price, $this->currency);
    }
}
