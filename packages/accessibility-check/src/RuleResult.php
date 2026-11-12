<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * The outcome of one rule on one content: it passes when it found nothing.
 */
final readonly class RuleResult
{
    public function __construct(
        public string $rule,
        public SuccessCriterion $criterion,
        public Severity $severity,
        public int $findings,
    ) {
    }

    public function passed(): bool
    {
        return 0 === $this->findings;
    }

    /**
     * @return array{rule: string, criterion: string, level: string, severity: string, passed: bool, findings: int}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'criterion' => $this->criterion->value,
            'level' => $this->criterion->level()->value,
            'severity' => $this->severity->value,
            'passed' => $this->passed(),
            'findings' => $this->findings,
        ];
    }
}
