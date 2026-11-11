<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Edit;

use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * The definitions an admin creates and edits, usually a database table that is also one of the evaluator's
 * providers. Each write is atomic and conditional, so two operators cannot overwrite each other.
 */
interface WritableFlagDefinitions
{
    public function findRevised(FlagName $flag): ?RevisedDefinition;

    /**
     * @return bool whether the definition was stored; `false` when the store already holds a flag of that name
     */
    public function create(FlagDefinition $definition): bool;

    /**
     * Replaces the definition **only if its revision is still `$revision`**, and increments it.
     *
     * @return bool whether the definition was replaced; `false` when it changed since, or no longer exists
     */
    public function replace(FlagDefinition $definition, int $revision): bool;
}
