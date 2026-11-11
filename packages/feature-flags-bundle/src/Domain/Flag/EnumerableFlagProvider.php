<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * A provider that can list every flag it knows: the configuration, a database table. A remote service that only
 * answers for one flag at a time stays a plain {@see FlagProvider}, and its flags do not appear in a listing.
 */
interface EnumerableFlagProvider extends FlagProvider
{
    /**
     * @return list<FlagDefinition>
     */
    public function all(): array;
}
