<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

trait AsEnumerableEnumTrait
{
    use AsCollectableEnumTrait;
    use AsComparableEnumTrait;
    use AsDefaultableEnumTrait;
    use AsFromambleEnumTrait;
    use AsInvocableEnumTrait;
    use AsMetadatableEnumTrait;
    use AsNameableEnumTrait;
    use AsOrdinalEnumTrait;
    use AsParsableEnumTrait;
    use AsSelectableEnumTrait;
    use AsSelfAwareableEnumTrait;
    use AsStatefulEnumTrait;
    use AsValuableEnumTrait;
}
