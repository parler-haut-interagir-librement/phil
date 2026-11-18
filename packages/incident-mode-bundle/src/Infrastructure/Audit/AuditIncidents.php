<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Audit;

use DateTimeInterface;
use InvalidArgumentException;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\LiftReason;

/**
 * Writes the incidents to the trail of phil/audit-bundle: who started, extended or lifted which incident, its preset,
 * what it suspends and its end — identifiers only, never the reason, which may hold personal words. An expiry is
 * recorded without an actor. An actor whose identifier the trail does not index — longer than 128 characters, or with
 * characters other than letters, digits, ".", "_", ":" and "-" — is left out of the row.
 */
final readonly class AuditIncidents
{
    public const string SUBJECT = 'incident';

    public const string DEFAULT_SEGMENT = 'incidents';

    public function __construct(
        private AuditTrail $trail,
        private string $segment = self::DEFAULT_SEGMENT,
    ) {
    }

    public function started(IncidentStarted $event): void
    {
        $activation = $event->activation;
        $this->trail->record($this->segment, [
            'preset' => $activation->preset,
            'restrictions' => implode(',', $activation->restrictionNames()),
            'ends_at' => $activation->endsAt->format(DateTimeInterface::ATOM),
        ], Facts::of('incident.started', $this->actor($activation->startedBy), Subject::of(self::SUBJECT, $activation->id)));
    }

    public function extended(IncidentExtended $event): void
    {
        $activation = $event->activation;
        $this->trail->record($this->segment, [
            'preset' => $activation->preset,
            'previous_end' => $event->previousEnd->format(DateTimeInterface::ATOM),
            'ends_at' => $activation->endsAt->format(DateTimeInterface::ATOM),
        ], Facts::of('incident.extended', $this->actor($event->by), Subject::of(self::SUBJECT, $activation->id)));
    }

    public function lifted(IncidentLifted $event): void
    {
        $activation = $event->activation;
        $expired = LiftReason::Expired === $activation->liftReason;
        $this->trail->record($this->segment, [
            'preset' => $activation->preset,
            'lifted_at' => $activation->liftedAt?->format(DateTimeInterface::ATOM),
        ], Facts::of($expired ? 'incident.expired' : 'incident.lifted', $this->actor($activation->liftedBy), Subject::of(self::SUBJECT, $activation->id)));
    }

    private function actor(?Actor $actor): ?string
    {
        if (!$actor instanceof Actor) {
            return null;
        }

        try {
            Facts::assertIdentifier('An actor', $actor->id);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $actor->id;
    }
}
