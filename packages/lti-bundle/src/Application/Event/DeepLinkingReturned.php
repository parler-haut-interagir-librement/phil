<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Platform\DeepLinkingResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deep linking response the platform verified: a listener places the items — resource links, line items — and
 * answers the browser, or the bundle shows a short page.
 */
final class DeepLinkingReturned
{
    private ?Response $response = null;

    public function __construct(
        public readonly DeepLinkingResult $result,
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
