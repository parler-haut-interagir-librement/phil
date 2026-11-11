<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Traits\AsDefaultableEnumTrait;

// Two defaults on purpose, for the run-time check: the declaration rule reports them.
// @phpstan-ignore philEnums.defaultCase
enum TwoDefaults
{
    use AsDefaultableEnumTrait;

    #[DefaultCase]
    case First;

    case Second;

    #[DefaultCase]
    case Third;
}
