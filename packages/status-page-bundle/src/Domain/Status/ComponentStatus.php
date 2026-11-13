<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\Status;

/**
 * The state of a component as visitors read it, from the best to the worst: operational, under maintenance, degraded,
 * partly down, down.
 */
enum ComponentStatus: string
{
    case Operational = 'operational';
    case Maintenance = 'maintenance';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';

    /**
     * The worse of the two, in the order of the cases: a major outage over a partial one, a partial outage over a
     * degraded service, a degraded service over a maintenance, a maintenance over an operational service.
     */
    public function worst(self $other): self
    {
        foreach ([self::MajorOutage, self::PartialOutage, self::Degraded, self::Maintenance] as $case) {
            if ($case === $this || $case === $other) {
                return $case;
            }
        }

        return self::Operational;
    }

    /**
     * Whether the state can be the impact of an incident: degraded, partly down or down.
     */
    public function isOutage(): bool
    {
        return self::Degraded === $this || self::PartialOutage === $this || self::MajorOutage === $this;
    }
}
