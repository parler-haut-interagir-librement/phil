<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Countable;

/**
 * What the preflight found, the findings about the whole document first, then page by page.
 */
final readonly class Report implements Countable
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        public array $findings = [],
    ) {
    }

    /**
     * Whether the file may go to the printer: no finding is an error.
     */
    public function passes(): bool
    {
        return [] === $this->errors();
    }

    /**
     * Whether nothing needs a look: no error and no warning.
     */
    public function isClean(): bool
    {
        return $this->passes() && [] === $this->warnings();
    }

    /**
     * @return list<Finding>
     */
    public function errors(): array
    {
        return $this->withSeverity(Severity::Error);
    }

    /**
     * @return list<Finding>
     */
    public function warnings(): array
    {
        return $this->withSeverity(Severity::Warning);
    }

    /**
     * @return list<Finding>
     */
    public function notices(): array
    {
        return $this->withSeverity(Severity::Notice);
    }

    /**
     * The findings about one page, numbered from 1; null for those about the whole document.
     *
     * @return list<Finding>
     */
    public function forPage(?int $page): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $page === $finding->page));
    }

    public function count(): int
    {
        return \count($this->findings);
    }

    /**
     * @return list<array{severity: string, code: string, message: string, page: ?int, subject: ?string, parameters: array<string, int|float|string>}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Finding $finding): array => $finding->toArray(), $this->findings);
    }

    /**
     * @return list<Finding>
     */
    private function withSeverity(Severity $severity): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $severity === $finding->severity));
    }
}
