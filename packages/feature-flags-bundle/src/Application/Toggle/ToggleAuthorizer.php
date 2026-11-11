<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Toggle;

use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;

/**
 * Decides whether an actor may change a flag: toggle it, create it, or edit its rules, experiment and metadata.
 * The adapter belongs to the application: which permission each change needs, and which flags — those that
 * force a circuit open, for instance — need a stronger confirmation on top of it.
 */
interface ToggleAuthorizer
{
    /**
     * @throws ToggleRefused
     */
    public function authorize(ToggleRequest $request): void;

    /**
     * @throws ToggleRefused
     */
    public function authorizeEdit(EditRequest $request): void;
}
