<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

enum LocalSessionEndReason: string
{
    case ProviderLogout = 'provider_logout';
    case LifetimeExceeded = 'lifetime_exceeded';
}
