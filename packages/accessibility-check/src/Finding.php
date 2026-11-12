<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * One problem the check found: the rule that found it, the success criterion of WCAG it fails, how grave it is, what it
 * says and where it is.
 */
final readonly class Finding
{
    public function __construct(
        public string $rule,
        public SuccessCriterion $criterion,
        public Severity $severity,
        public Message $message,
        public Location $location,
    ) {
    }

    /**
     * @return array{rule: string, criterion: string, level: string, severity: string, message: string, messageKey: string, parameters: array<string, int|float|string>, location: array<string, int|string>}
     */
    public function toArray(string $message): array
    {
        return [
            'rule' => $this->rule,
            'criterion' => $this->criterion->value,
            'level' => $this->criterion->level()->value,
            'severity' => $this->severity->value,
            'message' => $message,
            'messageKey' => $this->message->key,
            'parameters' => $this->message->parameters,
            'location' => $this->location->toArray(),
        ];
    }
}
