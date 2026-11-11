<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Closure;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * A check that answers what its closure answers, or throws what it throws.
 */
final readonly class StubCheck implements HealthCheck
{
    /**
     * @param Closure(): CheckOutcome $check
     */
    public function __construct(
        private string $name,
        private Closure $check,
    ) {
    }

    public static function answering(string $name, CheckOutcome $outcome): self
    {
        return new self($name, static fn (): CheckOutcome => $outcome);
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        return ($this->check)();
    }
}
