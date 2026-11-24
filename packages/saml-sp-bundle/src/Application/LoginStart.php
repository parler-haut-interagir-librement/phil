<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Binding\Outbound;

/**
 * A sign-in started: how the browser carries the request to the identity provider, and the token the browser keeps in a
 * cookie when the request is bound to it.
 */
final readonly class LoginStart
{
    public function __construct(public Outbound $outbound, public ?string $browserToken)
    {
    }
}
