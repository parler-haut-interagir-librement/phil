<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\IncidentMode;

use Phil\IncidentMode\IncidentMode;
use Phil\ModerationBundle\Application\ReportSurge;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The incident mode of `phil/incident-mode-bundle`, as the queue of the moderation offers it: a link to the bundle's
 * administration page for the role that runs it, and a proposal when reports surge and no incident is in force. The
 * incidents themselves — their storage, presets, commands, page and checkpoints — are the bundle's.
 *
 * It keeps nothing between requests: every answer reads the incidents in force and the role of the member at that
 * moment.
 */
final readonly class IncidentProposal
{
    /** The administration page of `phil/incident-mode-bundle`. */
    public const string ROUTE = 'phil_incident_mode_admin';

    public function __construct(
        private IncidentMode $incidents,
        private ReportSurge $surge,
        private AuthorizationCheckerInterface $authorization,
        private string $role,
    ) {
    }

    /**
     * Whether the member of the request runs the incident mode: the role of its administration.
     */
    public function isOffered(): bool
    {
        return $this->authorization->isGranted($this->role);
    }

    /**
     * Whether to propose an incident to the member: they run the incident mode, reports surge and none is in force.
     */
    public function isProposed(): bool
    {
        return $this->isOffered() && $this->surge->isSurging() && [] === $this->incidents->inForceNow();
    }
}
