<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\ValueObject;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * The tokens one task spent: those the model read, and those it wrote.
 */
final readonly class Tokens
{
    /**
     * @throws InvalidAiValue when a count is negative
     */
    public function __construct(
        public int $input,
        public int $output,
    ) {
        if ($input < 0 || $output < 0) {
            throw InvalidAiValue::because(\sprintf('A token count is zero or more; got %d in and %d out.', $input, $output));
        }
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public function total(): int
    {
        return $this->input + $this->output;
    }
}
