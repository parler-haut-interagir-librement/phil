<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\IncidentMode;

use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\Preset;
use Phil\ModerationBundle\Application\Moderator;
use Phil\ModerationBundle\Application\ReportSurge;

/**
 * The incident mode as the back office offers it, when `phil/incident-mode` is installed: the incidents in force, the
 * presets with their durations, a proposal when reports surge, and the moderator as the actor of what they start or
 * lift.
 */
final readonly class IncidentPanel
{
    public function __construct(
        private IncidentMode $incidents,
        private ReportSurge $surge,
    ) {
    }

    /**
     * @return list<Activation>
     */
    public function inForce(): array
    {
        return $this->incidents->inForceNow();
    }

    /**
     * @return list<Preset>
     */
    public function presets(): array
    {
        return $this->incidents->presets()->all();
    }

    /**
     * Whether to propose an incident: reports surge and none is in force.
     */
    public function isProposed(): bool
    {
        return $this->surge->isSurging() && [] === $this->incidents->inForceNow();
    }

    public function surge(): ReportSurge
    {
        return $this->surge;
    }

    /**
     * @return list<Activation>
     */
    public function journal(int $limit): array
    {
        return $this->incidents->journal(max(1, $limit));
    }

    public function start(Moderator $moderator, string $preset, int $minutes, ?string $reason): Activation
    {
        return $this->incidents->start($preset, Duration::ofMinutes($minutes), Actor::of($moderator->member->value), $reason);
    }

    public function lift(Moderator $moderator, string $id): Activation
    {
        return $this->incidents->lift($id, Actor::of($moderator->member->value));
    }
}
