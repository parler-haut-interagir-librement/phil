<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A group of buyers sold to on terms of their own: a share off the base prices of products, and an order a minimum
 * it must reach. Its own price list, when it has one, is a price book of the catalogue naming the group, which comes
 * before the group's share.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wholesale_group')]
final class CustomerGroup
{
    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'discount', type: 'integer')]
        private int $discountPartsPerMillion,
        #[ORM\Column(name: 'minimum_order', type: 'bigint')]
        private int $minimumOrderMinorUnits,
    ) {
    }

    /**
     * @param int $discount the share off the base prices, in parts per million: `250_000` is 25 %
     */
    public static function of(string $code, string $name, Currency $currency, int $discount = 0, ?Money $minimumOrder = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a customer group: 1 to 64 visible ASCII characters.', $code));
        }

        $minimumOrder ??= Money::zero($currency);

        return new self($code, self::checkedName($code, $name), $currency->code, self::checkedDiscount($discount), self::checkedMinimum($code, $currency, $minimumOrder));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function discount(): int
    {
        return $this->discountPartsPerMillion;
    }

    public function minimumOrder(): Money
    {
        return Money::of($this->minimumOrderMinorUnits, $this->currency);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($this->code, $name);
    }

    public function changeTerms(int $discount, Money $minimumOrder): void
    {
        $this->discountPartsPerMillion = self::checkedDiscount($discount);
        $this->minimumOrderMinorUnits = self::checkedMinimum($this->code, $this->currency, $minimumOrder);
    }

    /**
     * The unit price for the group: the base price less its share, rounded to the closest minor unit.
     */
    public function priceFrom(Money $basePrice): Money
    {
        return $basePrice->allocate(1_000_000 - $this->discountPartsPerMillion, $this->discountPartsPerMillion)[0];
    }

    private static function checkedName(string $code, string $name): string
    {
        if ('' === trim($name)) {
            throw InvalidValue::because(\sprintf('The customer group %s has a name.', $code));
        }

        return $name;
    }

    private static function checkedDiscount(int $discount): int
    {
        if ($discount < 0 || $discount >= 1_000_000) {
            throw InvalidValue::because(\sprintf('A group takes 0 to 999999 parts per million off; %d is refused.', $discount));
        }

        return $discount;
    }

    private static function checkedMinimum(string $code, Currency $currency, Money $minimumOrder): int
    {
        if (!$minimumOrder->currency->equals($currency) || $minimumOrder->isNegative()) {
            throw InvalidValue::because(\sprintf('The minimum order of %s is zero or more, in %s.', $code, $currency->code));
        }

        return $minimumOrder->minorUnits;
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }
}
