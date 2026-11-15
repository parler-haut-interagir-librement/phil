<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use Phil\Lti\Message\Launch;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user a verified launch signs in, on the tool: the application's own account for the person, found or made from
 * the launch. Without one, the bundle signs in an `LtiUser` that lives in the session only.
 */
interface LaunchUsers
{
    public function user(Launch $launch): UserInterface;
}
