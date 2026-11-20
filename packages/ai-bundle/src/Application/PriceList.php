<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Price;
use Phil\AiBundle\Domain\ValueObject\Tokens;

/**
 * What each model charges, all in one currency; a model the list does not price costs nothing in the journal.
 */
final readonly class PriceList
{
    /**
     * @param array<string, Price> $prices by model
     *
     * @throws InvalidAiValue when the currency is malformed or a price is in another one
     */
    public function __construct(
        private string $currency,
        private array $prices = [],
    ) {
        Cost::assertCurrency($currency);

        foreach ($prices as $model => $price) {
            if ($price->currency !== $currency) {
                throw InvalidAiValue::because(\sprintf('The model "%s" is priced in %s, the list in %s.', $model, $price->currency, $currency));
            }
        }
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function costOf(string $model, Tokens $tokens): Cost
    {
        return ($this->prices[$model] ?? new Price(0, 0, $this->currency))->costOf($tokens);
    }
}
