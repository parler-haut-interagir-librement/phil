<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

/**
 * The answer of a rule, with its trace: every condition evaluated or skipped, and the leaves that decided.
 */
final readonly class Result
{
    public function __construct(
        public bool $passed,
        public Step $trace,
        public int $evaluations,
    ) {
    }

    /**
     * The leaves that decided the answer: when the rule fails, the branch that failed and the reason of each leaf.
     *
     * @return list<Step>
     */
    public function explanation(): array
    {
        return $this->trace->decisive();
    }
}
