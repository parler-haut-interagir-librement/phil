<?php

declare(strict_types=1);

namespace Phil\FormLogic\Logic;

use Phil\FormLogic\Evaluation\Values;

/**
 * What the server decides of an answer: the fields its conditions hide, the answer without them, and the computed
 * variables, to store with the answer.
 *
 * @phpstan-import-type Value from Values
 */
final readonly class Verdict
{
    /**
     * @param array<array-key, mixed> $answer   the answer, the values of the hidden fields removed
     * @param list<string>            $hidden   the fields whose condition does not hold, in the order of the logic
     * @param array<string, Value>    $computed the computed variables, in the order of the logic
     */
    public function __construct(public array $answer, public array $hidden, public array $computed)
    {
    }

    public function isShown(string $field): bool
    {
        return !\in_array($field, $this->hidden, true);
    }
}
