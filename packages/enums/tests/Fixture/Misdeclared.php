<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\BitFlags;
use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\RequiredMeta;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * Every mistake the declaration rule reports, once each — the ignores below fail the analysis if the rule stops
 * reporting one; the cases also miss the required weight. Never loaded at run time: its negative weight would throw.
 */
// @phpstan-ignore philEnums.defaultCase, philEnums.ambiguousAlias, philEnums.ambiguousAlias, philEnums.ambiguousAlias, philEnums.negativeWeight, philEnums.notBitFlags, philEnums.notBitFlags, philEnums.requiredMetaUndeclared, philEnums.requiredMeta, philEnums.requiredMeta, philEnums.requiredMeta
#[BitFlags]
#[Meta(Label::class)]
#[RequiredMeta(Label::class, Weight::class)]
enum Misdeclared: int
{
    use AsMetadatableEnumTrait;

    #[Label('One')] #[DefaultCase] #[Alias('Two')] #[Alias('first')]
    case One = 1;

    #[Label('Two')] #[DefaultCase] #[Alias('1')] #[Weight(-1)]
    case Two = 2;

    #[Label('Three')] #[Alias('first')]
    case Three = 3;

    #[Label('Zero')]
    case Zero = 0;
}
