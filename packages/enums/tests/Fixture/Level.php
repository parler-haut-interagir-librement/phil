<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Deprecated;
use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Traits\AsEnumerableEnumTrait;

/**
 * Levels in their order, a default, aliases — one of them the value of another case —, weights for random draws
 * — `High` never drawn — and a deprecated case, read through `from()` in the tests so that no test triggers its
 * deprecation.
 */
// The alias "high" of `Medium` is the value of `High`, on purpose: the declaration rule reports it.
// @phpstan-ignore philEnums.ambiguousAlias
#[Meta(Label::class, Weight::class)]
enum Level: string
{
    use AsEnumerableEnumTrait;

    #[Label('Low')] #[Alias('minor')] #[Weight(3)]
    case Low = 'low';

    #[Label('Medium')] #[Alias('high')] #[DefaultCase]
    case Medium = 'medium';

    #[Label('High')] #[Alias('major')] #[Alias('3')] #[Weight(0)]
    case High = 'high';

    #[Label('Critical')] #[Weight(2)]
    #[Deprecated]
    case Critical = 'critical';
}
