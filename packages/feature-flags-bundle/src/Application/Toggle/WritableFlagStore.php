<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Toggle;

use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagStateSource;

/**
 * The source of active states that a toggle writes to. Only the active state is toggled: the rules of a flag
 * are changed with the feature, not from a switch, and stay with the providers.
 */
interface WritableFlagStore extends FlagStateSource
{
    /**
     * Sets the active state **only if it is still `$expected`**, atomically (a conditional `UPDATE`, a
     * compare-and-set): two concurrent toggles cannot both apply, and the value before that the audit line
     * carries is the value the write replaced.
     *
     * @return bool whether the state was written; `false` when the current state is no longer `$expected`, or
     *              the store no longer holds the flag
     */
    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool;

    /**
     * Stores the active state of a flag the store does not hold yet, **only if it still does not**, atomically:
     * the first toggle of a flag declared elsewhere, or the initial state of a flag just created.
     *
     * @return bool whether the state was written; `false` when the store already holds the flag
     */
    public function insert(FlagName $flag, bool $active): bool;
}
