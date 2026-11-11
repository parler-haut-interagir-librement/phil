<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\RequiredMeta;
use Phil\Enums\Tests\Fixture\Attribute\Mass;

/**
 * An enum whose cases miss the metadata it requires: what the PHPStan rule reports, four times — the ignore below
 * fails the analysis if it stops — and what `Reflection::missingRequiredMeta()` lists.
 */
// @phpstan-ignore philEnums.requiredMeta, philEnums.requiredMeta, philEnums.requiredMeta, philEnums.requiredMeta
#[RequiredMeta(Label::class, Description::class)]
#[RequiredMeta(Mass::class)]
enum Incomplete
{
    #[Label('Whole')] #[Description('Carries all')] #[Mass(1)]
    case Whole;

    #[Label('Half')] #[Mass(2)]
    case Half;

    case Bare;
}
