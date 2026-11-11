<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Admin;

/**
 * Who is using the admin screens: the application's opaque identifier of the operator, taken from its own
 * authentication, or `null` when nobody is authenticated, in which case every screen is refused.
 *
 * The adapter belongs to the application, which also puts the screens behind its firewall.
 */
interface ActorProvider
{
    public function current(): ?string;
}
