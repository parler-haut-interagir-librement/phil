<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use Phil\MonitoringBundle\Domain\Exception\InvalidValue;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;

/**
 * One check as it ran: its name, its outcome, and how long it took, in milliseconds.
 */
final readonly class CheckResult
{
    /**
     * @var non-empty-string
     */
    public string $name;

    public function __construct(
        string $name,
        public CheckOutcome $outcome,
        public int $durationMilliseconds,
    ) {
        $this->name = Name::check($name, 'check');

        if ($durationMilliseconds < 0) {
            throw InvalidValue::because('A check takes no less than 0 milliseconds.');
        }
    }

    public function status(): HealthStatus
    {
        return $this->outcome->status;
    }
}
