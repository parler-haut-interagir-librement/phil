<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * A source of flag definitions: the application configuration, a database table, a remote service.
 *
 * The adapters belong to the application. A provider that does not know a flag returns `null` and lets the
 * next provider answer; it does not invent an inactive definition, which would hide the next provider's.
 */
interface FlagProvider
{
    public function find(FlagName $name): ?FlagDefinition;
}
