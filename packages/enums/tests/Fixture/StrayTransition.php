<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\TransitionsTo;
use Phil\Enums\Traits\AsStatefulEnumTrait;

/**
 * A case that names a case of another enum as its next state.
 */
enum StrayTransition
{
    use AsStatefulEnumTrait;

    #[TransitionsTo(Lifecycle::Draft)]
    case Lost;
}
