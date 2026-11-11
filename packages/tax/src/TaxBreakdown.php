<?php

declare(strict_types=1);

namespace Phil\Tax;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Tax\Exception\InvalidBreakdown;

/**
 * Several taxes on the same base, itemised separately on the invoice.
 *
 * **Every rate applies to the same base, never to the base plus another tax.** Compounding is a different
 * rule and would need a different type. A rate code appears at most once, because a receipt shows one line
 * per tax and a credit note reverses it line by line.
 */
final readonly class TaxBreakdown
{
    /**
     * @param list<TaxLine> $lines
     */
    private function __construct(
        public Money $base,
        public array $lines,
    ) {
    }

    /**
     * @param list<TaxLine> $lines
     */
    public static function of(Money $base, array $lines): self
    {
        $codes = [];

        foreach ($lines as $line) {
            if (!$line->base->equals($base)) {
                if (!$line->base->currency->equals($base->currency)) {
                    throw CurrencyMismatch::between($base->currency, $line->base->currency);
                }

                throw InvalidBreakdown::anotherBase($line->rate->code);
            }

            if (isset($codes[$line->rate->code])) {
                throw InvalidBreakdown::duplicateRate($line->rate->code);
            }

            $codes[$line->rate->code] = $line;
        }

        return new self($base, $lines);
    }

    /**
     * The line of a rate code, or `null` when the breakdown has none.
     */
    public function line(string $code): ?TaxLine
    {
        return array_find($this->lines, static fn (TaxLine $line): bool => $line->rate->code === $code);
    }

    public function totalTax(): Money
    {
        $total = Money::zero($this->base->currency);

        foreach ($this->lines as $line) {
            $total = $total->add($line->amount);
        }

        return $total;
    }

    public function totalWithTax(): Money
    {
        return $this->base->add($this->totalTax());
    }

    /**
     * The breakdown a credit note carries: the same lines, each negated, so that the itemised taxes are
     * reversed rather than the invoice being edited.
     */
    public function reversed(): self
    {
        return new self($this->base->negate(), array_map(static fn (TaxLine $line): TaxLine => $line->reversed(), $this->lines));
    }
}
