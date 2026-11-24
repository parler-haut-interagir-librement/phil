<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Event;

use Phil\SamlSp\Message\LogoutRequest;

/**
 * An identity provider signed a person out. The bundle ends the session of the browser that carried the request; the
 * application ends the other sessions of that person, by the name identifier and the session indexes of the request.
 */
final readonly class SamlLogoutReceived
{
    public function __construct(public string $identityProvider, public LogoutRequest $request)
    {
    }
}
