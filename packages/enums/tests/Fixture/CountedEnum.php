<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Counted;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Counted::class)]
enum CountedEnum
{
    use AsMetadatableEnumTrait;

    #[Counted('first')]
    case FIRST;

    #[Counted('second')] #[Counted('also')]
    case SECOND;
}
