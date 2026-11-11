<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

/**
 * What the validator found in a graph, errors and warnings in the order of the graph.
 */
final readonly class Report
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(public array $issues = [])
    {
    }

    /**
     * @return list<Issue>
     */
    public function errors(): array
    {
        return $this->of(Severity::Error);
    }

    /**
     * @return list<Issue>
     */
    public function warnings(): array
    {
        return $this->of(Severity::Warning);
    }

    /**
     * Whether the graph has no error; it may still have warnings.
     */
    public function isValid(): bool
    {
        return [] === $this->errors();
    }

    /**
     * @return list<array{severity: string, code: string, node: string, property: ?string, message: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Issue $issue): array => $issue->toArray(), $this->issues);
    }

    /**
     * @return list<Issue>
     */
    private function of(Severity $severity): array
    {
        return array_values(array_filter($this->issues, static fn (Issue $issue): bool => $severity === $issue->severity));
    }
}
