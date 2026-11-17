<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Platform\DeepLinkingResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deep linking response the platform verified and kept: a listener places the items — resource links, line items —
 * and answers the browser, or the bundle shows a short page.
 *
 * `key` names the response for good: a response whose handling failed is told again under the same key, with
 * `resumed` true, by `DeepLinkingHandler::resume()` or `phil:lti:resume-deep-linking`, outside the browser's request.
 * A listener is idempotent by this key: it places the items of a key once.
 */
final class DeepLinkingReturned
{
    public readonly string $key;

    private ?Response $response = null;

    public function __construct(
        public readonly DeepLinkingResult $result,
        public readonly bool $resumed = false,
    ) {
        $this->key = $result->key;
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
