<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Message\Launch;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A launch the tool verified, its user signed in: a listener answers it — the page of the resource link, the picker of a
 * deep linking request —, or the bundle sends the browser to the target of the launch.
 */
final class LaunchReceived
{
    private ?Response $response = null;

    public function __construct(
        public readonly Launch $launch,
        public readonly Request $request,
    ) {
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function response(): ?Response
    {
        return $this->response;
    }
}
