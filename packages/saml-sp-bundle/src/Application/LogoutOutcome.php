<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSp\Message\LogoutRequest;

/**
 * What a logout message of an identity provider leads to: for its request, the session to end and the response to send
 * back; for its response, the local path the person goes to.
 */
final readonly class LogoutOutcome
{
    public function __construct(
        public ?LogoutRequest $request = null,
        public ?Outbound $response = null,
        public ?string $target = null,
    ) {
    }
}
