<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A discount an employee grants by hand, on one line or on the whole order, as a fixed amount or as a share, with
 * its reason and who granted it: what a store and a point of sale both use when a price is lowered at the counter.
 *
 * `ManualDiscountProcessor` turns it into `Promotion` adjustments before the taxes; a discount on the whole order is
 * shared between its lines in proportion to their amounts, so that each line is taxed once discounted. A discount
 * never takes more than what it applies to.
 */
final readonly class ManualDiscount
{
    /** A whole share, in basis points: 100 %. */
    public const int WHOLE = 10_000;

    private function __construct(
        public string $key,
        public ?SellableReference $line,
        public ?Money $amount,
        public ?int $basisPoints,
        public string $reason,
        public string $grantedBy,
    ) {
    }

    /**
     * A fixed amount off, on a line or on the whole order.
     *
     * @param string $key       names the discount within its order, to withdraw it: up to 32 letters, digits, "_" or "-"
     * @param string $grantedBy the stable identifier of the employee
     */
    public static function amount(string $key, Money $amount, string $reason, string $grantedBy, ?SellableReference $line = null): self
    {
        if (!$amount->isPositive()) {
            throw InvalidValue::because(\sprintf('The manual discount "%s" takes a positive amount off.', $key));
        }

        return new self(self::checkedKey($key), $line, $amount, null, self::checkedReason($reason), Identifier::check($grantedBy, 'employee'));
    }

    /**
     * A share off, in basis points — 1,500 for 15 % —, on a line or on the whole order.
     */
    public static function share(string $key, int $basisPoints, string $reason, string $grantedBy, ?SellableReference $line = null): self
    {
        if ($basisPoints < 1 || $basisPoints > self::WHOLE) {
            throw InvalidValue::because(\sprintf('The manual discount "%s" takes 1 to 10000 basis points off.', $key));
        }

        return new self(self::checkedKey($key), $line, null, $basisPoints, self::checkedReason($reason), Identifier::check($grantedBy, 'employee'));
    }

    /**
     * @param array{key: string, line: string|null, amount: int|null, currency: string|null, basis_points: int|null, reason: string, granted_by: string} $stored
     */
    public static function fromArray(array $stored): self
    {
        $line = null === $stored['line'] ? null : SellableReference::fromValue($stored['line']);

        if (null !== $stored['amount'] && null !== $stored['currency']) {
            return self::amount($stored['key'], Money::of($stored['amount'], Currency::of($stored['currency'])), $stored['reason'], $stored['granted_by'], $line);
        }

        if (null === $stored['basis_points']) {
            throw InvalidValue::because(\sprintf('The stored manual discount "%s" holds neither an amount nor a share.', $stored['key']));
        }

        return self::share($stored['key'], $stored['basis_points'], $stored['reason'], $stored['granted_by'], $line);
    }

    /**
     * @return array{key: string, line: string|null, amount: int|null, currency: string|null, basis_points: int|null, reason: string, granted_by: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'line' => $this->line?->value(),
            'amount' => $this->amount?->minorUnits,
            'currency' => $this->amount?->currency->code,
            'basis_points' => $this->basisPoints,
            'reason' => $this->reason,
            'granted_by' => $this->grantedBy,
        ];
    }

    /**
     * What the discount takes off a base: its amount, or its share rounded down to the minor unit, never more than the
     * base itself. A base of zero or less gives zero.
     */
    public function of(Money $base): Money
    {
        if (!$base->isPositive()) {
            return Money::zero($base->currency);
        }

        if ($this->amount instanceof Money) {
            return $this->amount->isGreaterThan($base) ? $base : $this->amount;
        }

        $share = intdiv($base->minorUnits * (int) $this->basisPoints, self::WHOLE);

        return Money::of($share, $base->currency);
    }

    private static function checkedKey(string $key): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $key)) {
            throw InvalidValue::because(\sprintf('"%s" is not the key of a manual discount: 1 to 32 letters, digits, "_" or "-".', $key));
        }

        return $key;
    }

    private static function checkedReason(string $reason): string
    {
        if ('' === trim($reason) || mb_strlen($reason) > 255 || 1 !== preg_match('/^\P{Cc}+$/Du', $reason)) {
            throw InvalidValue::because('A manual discount has a reason: 1 to 255 characters on one line.');
        }

        return $reason;
    }
}
