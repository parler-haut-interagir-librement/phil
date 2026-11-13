<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * Reads the conditions of filters and branches written in one language.
 *
 * The variables a condition sees are `payload` (the trigger's identifiers), `outputs` (what each action step gave
 * back, by step id) and `subject`.
 */
interface ConditionEvaluator
{
    /**
     * The language's name, as a definition's condition gives it.
     */
    public function language(): string;

    /**
     * Refuses, when a definition is validated, a condition that would not evaluate.
     *
     * @throws InvalidDefinition
     */
    public function check(string $source): void;

    /**
     * @param array{payload: array<string, int|bool|string|null>, outputs: array<string, array<string, int|bool|string|null>>, subject: string|null} $variables
     */
    public function evaluate(string $source, array $variables): bool;
}
