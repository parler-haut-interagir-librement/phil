<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Routing;

use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

/**
 * The flags in a route condition, under the name `feature_flags`, so that a route matches only while its flag is
 * on — and the next route with the same path, the current feature, answers otherwise:
 *
 * ```yaml
 * checkout_v2:
 *     path: /checkout
 *     controller: App\Controller\CheckoutV2Controller
 *     condition: "service('feature_flags').enabled('new-checkout')"
 * ```
 *
 * **Only a flag that reads neither the subject nor the tenant fits a route condition.** The router matches the
 * request before the firewall loads the user, and before the listeners that set the tenant, so the context of a
 * route condition does not know who asks. A flag with a rule or an experiment on the subject or the tenant is
 * refused with `FlagNotRoutable` when the condition first evaluates it, rather than answered for an anonymous
 * context; such a flag guards its controller with `#[IsFeatureEnabled]`, which runs after the firewall.
 */
final readonly class FlagRoutingCondition
{
    public function __construct(
        private FlagEvaluator $flags,
    ) {
    }

    public function enabled(string $flag): bool
    {
        return $this->flags->isEnabled($this->routable($flag));
    }

    public function variant(string $flag): ?string
    {
        return $this->flags->variant($this->routable($flag))?->value;
    }

    private function routable(string $flag): FlagName
    {
        $name = FlagName::of($flag);

        if (true === $this->flags->definition($name)?->readsAnyOf(TargetingAttribute::Subject, TargetingAttribute::Tenant)) {
            throw FlagNotRoutable::targeted($name);
        }

        return $name;
    }
}
