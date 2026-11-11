<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\TransitionsTo;
use Phil\Enums\Traits\AsStatefulEnumTrait;

/**
 * A lifecycle with one initial state, a loop — review sends work back, which returns to review — and a final
 * state.
 */
enum Lifecycle: string
{
    use AsStatefulEnumTrait;

    #[TransitionsTo(self::Review)]
    case Draft = 'draft';

    #[TransitionsTo(self::Published, self::Rework)]
    case Review = 'review';

    #[TransitionsTo(self::Review)]
    case Rework = 'rework';

    #[TransitionsTo(self::Archived)]
    case Published = 'published';

    case Archived = 'archived';
}
