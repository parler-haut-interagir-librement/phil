<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A buyer's credit at the shop: buying on account up to a limit, each purchase due after the terms — 30 days for
 * "net 30". What the buyer owes moves with each purchase, payment and credit.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wholesale_credit_account')]
final class CreditAccount
{
    public Money $limit {
        get => Money::of($this->limitMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $owed {
        get => Money::of($this->owedMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'owed', type: 'bigint')]
    private int $owedMinorUnits = 0;

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'buyer', length: 255)]
        public readonly string $buyer,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'credit_limit', type: 'bigint')]
        private int $limitMinorUnits,
        #[ORM\Column(name: 'terms_days', type: 'smallint')]
        private int $termsDays,
    ) {
    }

    public static function open(string $buyer, Money $limit, int $termsDays): self
    {
        if ('' === trim($buyer) || mb_strlen($buyer) > 255) {
            throw InvalidValue::because('A credit account belongs to a buyer, named in 1 to 255 characters.');
        }

        return new self($buyer, $limit->currency->code, self::checkedLimit($limit), self::checkedTerms($termsDays));
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    public function termsDays(): int
    {
        return $this->termsDays;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function available(): Money
    {
        $available = $this->limit->subtract($this->owed);

        return $available->isNegative() ? Money::zero($available->currency) : $available;
    }

    public function change(Money $limit, int $termsDays): void
    {
        if (!$limit->currency->equals($this->currency())) {
            throw InvalidValue::because(\sprintf('The credit of %s is in %s.', $this->buyer, $this->currencyCode));
        }

        $this->limitMinorUnits = self::checkedLimit($limit);
        $this->termsDays = self::checkedTerms($termsDays);
    }

    /**
     * Owes a purchase on account, within the limit.
     */
    public function charge(Money $amount): void
    {
        $this->checkAmount($amount);

        if (!$this->active) {
            throw WholesaleRefused::noAccount($this->buyer);
        }

        if ($amount->isGreaterThan($this->available())) {
            throw WholesaleRefused::overLimit($this->buyer, $amount->toDecimal(), $this->available()->toDecimal());
        }

        $this->owedMinorUnits += $amount->minorUnits;
    }

    /**
     * Owes less: a payment received, or a purchase refunded.
     */
    public function reduce(Money $amount): void
    {
        $this->checkAmount($amount);
        $this->owedMinorUnits = max(0, $this->owedMinorUnits - $amount->minorUnits);
    }

    public function suspend(): void
    {
        $this->active = false;
    }

    public function resume(): void
    {
        $this->active = true;
    }

    private function checkAmount(Money $amount): void
    {
        if (!$amount->isPositive() || !$amount->currency->equals($this->currency())) {
            throw InvalidValue::because(\sprintf('An amount on the account of %s is positive, in %s.', $this->buyer, $this->currencyCode));
        }
    }

    private static function checkedLimit(Money $limit): int
    {
        if ($limit->isNegative()) {
            throw InvalidValue::because('A credit limit is zero or more.');
        }

        return $limit->minorUnits;
    }

    private static function checkedTerms(int $termsDays): int
    {
        if ($termsDays < 0 || $termsDays > 365) {
            throw InvalidValue::because(\sprintf('Credit terms are 0 to 365 days; %d is refused.', $termsDays));
        }

        return $termsDays;
    }
}
