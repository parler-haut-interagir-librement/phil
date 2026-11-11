<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Routing;

use LogicException;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * A route condition named a flag that targets subjects or tenants. Routes are matched before the firewall loads
 * the user, so such a flag would be evaluated against a context that does not know who asks yet: guard the
 * controller with `#[IsFeatureEnabled]` instead, which runs after the firewall.
 */
final class FlagNotRoutable extends LogicException
{
    public static function targeted(FlagName $flag): self
    {
        return new self(\sprintf(
            'The flag "%s" targets subjects or tenants, so a route condition cannot read it: routes are matched before the firewall knows the user. Guard the controller with #[IsFeatureEnabled(\'%1$s\')] instead.',
            $flag->value,
        ));
    }
}
