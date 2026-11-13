<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;

/**
 * A component and the state it is shown in.
 */
final readonly class ComponentState
{
    public function __construct(
        public Component $component,
        public ComponentStatus $status,
    ) {
    }
}
