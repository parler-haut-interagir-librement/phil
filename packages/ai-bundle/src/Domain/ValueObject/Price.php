<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\ValueObject;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * What a model charges, in micros of a currency per million tokens read and per million tokens written — a model at
 * 3.00 per million input tokens is priced at 3 000 000 —, and per call, for a model that charges by the image or the
 * request whatever its tokens.
 */
final readonly class Price
{
    private const int MILLION = 1_000_000;

    /**
     * @throws InvalidAiValue
     */
    public function __construct(
        public int $inputPerMillion,
        public int $outputPerMillion,
        public string $currency,
        public int $perCall = 0,
    ) {
        if ($inputPerMillion < 0 || $outputPerMillion < 0 || $perCall < 0) {
            throw InvalidAiValue::because('A price is zero or more.');
        }

        Cost::assertCurrency($currency);
    }

    /**
     * The cost of one call that counted these tokens, rounded up to the next micro.
     */
    public function costOf(Tokens $tokens): Cost
    {
        $amount = $tokens->input * $this->inputPerMillion + $tokens->output * $this->outputPerMillion;

        return new Cost(intdiv($amount + self::MILLION - 1, self::MILLION) + $this->perCall, $this->currency);
    }
}
