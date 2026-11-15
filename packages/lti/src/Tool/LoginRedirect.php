<?php

declare(strict_types=1);

namespace Phil\Lti\Tool;

use Phil\Lti\Security\LoginState;
use Phil\Url\Url;

/**
 * Where the tool sends the browser after a login initiation — the authorization endpoint of the platform with the
 * authentication request in its query —, and the state it remembered.
 */
final readonly class LoginRedirect
{
    public function __construct(
        public Url $url,
        public LoginState $state,
    ) {
    }
}
