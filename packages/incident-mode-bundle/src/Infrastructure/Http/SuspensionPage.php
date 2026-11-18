<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Http;

use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Exception\SuspendedByIncident;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The public pages of the incident mode: the page of a refusal, with the status and the `Retry-After` of its
 * exception; the page that says what is suspended now and until when; and the same notice as a fragment, for a page
 * that includes it. Each says it for the member of the request, in their language, and is never kept by a shared
 * cache.
 */
final readonly class SuspensionPage
{
    public const string STATUS_ROUTE = 'phil_incident_mode_status';

    public const string NOTICE_ROUTE = 'phil_incident_mode_notice';

    public function __construct(
        private Checkpoint $checkpoint,
        private Environment $twig,
        private string $layout,
    ) {
    }

    public function refusal(SuspendedByIncident $suspended): Response
    {
        return new Response(
            $this->twig->render('@PhilIncidentMode/suspended.html.twig', ['layout' => $this->layout, 'suspension' => $suspended->suspension, 'retry_after' => $suspended->retryAfter]),
            $suspended->getStatusCode(),
            [...$suspended->getHeaders(), 'Cache-Control' => 'no-store'],
        );
    }

    public function status(): Response
    {
        return new Response(
            $this->twig->render('@PhilIncidentMode/status.html.twig', ['layout' => $this->layout, 'suspensions' => $this->checkpoint->suspensions()]),
            Response::HTTP_OK,
            ['Cache-Control' => 'no-store'],
        );
    }

    public function notice(): Response
    {
        return new Response(
            $this->twig->render('@PhilIncidentMode/_notice.html.twig', ['suspensions' => $this->checkpoint->suspensions()]),
            Response::HTTP_OK,
            ['Cache-Control' => 'no-store'],
        );
    }
}
