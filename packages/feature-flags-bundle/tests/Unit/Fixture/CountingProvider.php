<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagProvider;

final class CountingProvider implements FlagProvider
{
    /** @var array<string, bool> */
    public array $flags = [];

    public int $lookups = 0;

    #[Override]
    public function find(FlagName $name): ?FlagDefinition
    {
        ++$this->lookups;

        return isset($this->flags[$name->value]) ? new FlagDefinition($name, $this->flags[$name->value]) : null;
    }
}
