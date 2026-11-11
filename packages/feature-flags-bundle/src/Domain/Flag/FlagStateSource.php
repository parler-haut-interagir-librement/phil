<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * Where the active state of a flag is read when it can be changed without a deploy, usually a database table.
 *
 * It holds the active state only, never the rules: the rules stay with the providers ({@see FlagProvider}), and
 * the state read here is applied to the definition a provider returns. So a flag deactivated and then
 * reactivated from the store gets back the exact rollout its provider declares.
 */
interface FlagStateSource
{
    /**
     * @return bool|null the current active state, or `null` when the source does not hold the flag, in which
     *                   case the provider's own state applies
     */
    public function isActive(FlagName $flag): ?bool;
}
