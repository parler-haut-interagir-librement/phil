<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Traits\AsCollectableEnumTrait;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * Cases declared out of every order but their own, to see filters and sorts at work: by name, by value and by
 * mass, which `Pluto` does not carry; and groups, `Earth` in two of them.
 */
#[Meta(Label::class, Mass::class, Group::class)]
enum Planet: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    #[Label('Mars')] #[Mass(6)] #[Group('inner')]
    case Mars = 'c-mars';

    #[Label('Earth')] #[Mass(60)] #[Group('inner')] #[Group('home')]
    case Earth = 'b-earth';

    #[Label('Mercury')] #[Mass(3)] #[Group('inner')]
    case Mercury = 'a-mercury';

    #[Label('Venus')] #[Mass(49)] #[Group('inner')]
    case Venus = 'd-venus';

    #[Label('Pluto')] #[Group('dwarf')]
    case Pluto = 'e-pluto';
}
