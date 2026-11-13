<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\IncidentUpdate;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;

/**
 * Components, incidents and maintenances around 2026-11-12 12:00 UTC, the present of the tests.
 */
final class Fixtures
{
    public const string NOW = '2026-11-12T12:00:00+00:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function at(string $modifier): DateTimeImmutable
    {
        return self::now()->modify($modifier);
    }

    /**
     * @return list<Component>
     */
    public static function components(): array
    {
        return [
            new Component('website', 'Website', 'The public pages'),
            new Component('api', 'API', null, 'Platform'),
            new Component('sign-in', 'Sign-in', null, 'Platform'),
        ];
    }

    /**
     * @param list<string> $components
     */
    public static function incident(string $id, ComponentStatus $impact, array $components, string $startedAt, ?string $resolvedAt = null): Incident
    {
        $updates = [new IncidentUpdate(self::at($startedAt), IncidentState::Investigating, 'We are looking into it.')];

        if (null !== $resolvedAt) {
            $updates[] = new IncidentUpdate(self::at($resolvedAt), IncidentState::Resolved, 'It works again.');
        }

        return new Incident($id, 'Incident '.$id, $impact, $components, $updates);
    }

    /**
     * @param list<string> $components
     */
    public static function maintenance(string $id, array $components, string $startsAt, string $endsAt): ScheduledMaintenance
    {
        return new ScheduledMaintenance($id, 'Maintenance '.$id, $components, self::at($startsAt), self::at($endsAt), 'Planned work.');
    }
}
