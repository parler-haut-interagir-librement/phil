<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\ValueObject;

use Phil\LicensingBundle\Domain\Compliance\ViolationCode;

/**
 * What the compliance check found: no violation, or each of them in the order they were found.
 */
final readonly class ComplianceReport
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public array $violations = [],
    ) {
    }

    public function isCompliant(): bool
    {
        return [] === $this->violations;
    }

    public function with(Violation $violation): self
    {
        return new self([...$this->violations, $violation]);
    }

    public function has(ViolationCode $code): bool
    {
        return array_any($this->violations, static fn (Violation $violation): bool => $violation->code === $code);
    }
}
