<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

/**
 * What a verification found, rule by rule in the order the rules ran, and the level it ran at.
 */
final readonly class Report
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        public RuleLevel $level,
        public array $findings,
    ) {
    }

    /**
     * The findings whose severity reaches `$threshold`.
     *
     * @return list<Finding>
     */
    public function reaching(Severity $threshold): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $finding->severity->reaches($threshold)));
    }

    /**
     * Whether the document fails at `$threshold`: one finding reaching it is enough.
     */
    public function fails(Severity $threshold = Severity::Error): bool
    {
        return [] !== $this->reaching($threshold);
    }

    /**
     * @return array{level: string, findings: list<array{rule: string, severity: string, location: string, message: string}>}
     */
    public function toArray(): array
    {
        return ['level' => $this->level->value, 'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $this->findings)];
    }
}
