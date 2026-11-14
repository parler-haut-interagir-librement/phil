<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Evaluation\Values;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * A constant: `null`, a boolean, a number or a text. A number is kept as JavaScript holds it — an integral number up to
 * 2^53 as an integer, a larger integer as the float nearest to it —, so both evaluators read the same one; a non-finite
 * number is refused.
 */
final readonly class Literal implements Expression
{
    public bool|int|float|string|null $value;

    /**
     * @throws InvalidLogic when the number is not finite
     */
    public function __construct(bool|int|float|string|null $value)
    {
        if (\is_int($value) || \is_float($value)) {
            $value = Values::number($value) ?? throw InvalidLogic::because('A number of a rule is finite.');
        }

        $this->value = $value;
    }

    #[Override]
    public function jsonLogic(): string|int|float|bool|null
    {
        return $this->value;
    }

    #[Override]
    public function children(): array
    {
        return [];
    }
}
