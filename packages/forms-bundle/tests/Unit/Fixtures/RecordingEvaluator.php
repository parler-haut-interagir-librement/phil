<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Fixtures;

use Override;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;

/**
 * An evaluator with a grammar of its own: it reads the field `team`, holds for any values, and keeps the conditions
 * it is asked to check.
 */
final class RecordingEvaluator implements ConditionEvaluator
{
    /** @var list<array{array<array-key, mixed>, list<string>}> */
    public array $checked = [];

    #[Override]
    public function fields(array $condition): array
    {
        return ['team'];
    }

    #[Override]
    public function check(array $condition, array $fields): void
    {
        $this->checked[] = [$condition, array_keys($fields)];
    }

    #[Override]
    public function holds(array $condition, array $values): bool
    {
        return true;
    }
}
