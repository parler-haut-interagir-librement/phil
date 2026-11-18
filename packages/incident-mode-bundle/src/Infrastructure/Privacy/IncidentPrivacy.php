<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The incident mode's part of an access or erasure request, for the member the subject names: the incidents they
 * started or lifted — preset, start, end, lifting, and which of the two they did —, never another actor nor a reason.
 * Erasing takes the member out of those incidents, which stay in the journal without them; running it again changes
 * nothing and reports the same.
 */
final readonly class IncidentPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'incident_mode';

    public function __construct(
        private ActivationStore $store,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $actor = $this->actor($subject);

        if (!$actor instanceof Actor) {
            return [];
        }

        $incidents = array_map(static fn (Activation $activation): array => [
            'preset' => $activation->preset,
            'restrictions' => $activation->restrictionNames(),
            'started_at' => $activation->startedAt->format(DateTimeInterface::ATOM),
            'ends_at' => $activation->endsAt->format(DateTimeInterface::ATOM),
            'lifted_at' => $activation->liftedAt?->format(DateTimeInterface::ATOM),
            'started_by_me' => $activation->startedBy?->id === $actor->id,
            'lifted_by_me' => $activation->liftedBy?->id === $actor->id,
        ], $this->store->ofActor($actor));

        return [] === $incidents ? [] : ['incidents' => $incidents];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $actor = $this->actor($subject);

        if ($actor instanceof Actor) {
            $this->store->forgetActor($actor);
        }

        return ErasureReport::of(self::MODULE, [IncidentTables::ACTIVATIONS => ErasureEffect::Pseudonymised]);
    }

    /**
     * The subject as an actor; `null` when the incident mode could not have kept them, such as an e-mail address.
     */
    private function actor(DataSubject $subject): ?Actor
    {
        try {
            return Actor::of($subject->value);
        } catch (InvalidIncident) {
            return null;
        }
    }
}
