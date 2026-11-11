<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Application\Edit\WritableFlagDefinitions;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\EnumerableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

final class InMemoryDefinitions implements EnumerableFlagProvider, WritableFlagDefinitions
{
    /** @var array<string, RevisedDefinition> */
    public array $definitions = [];

    /** Another operator saves between the read and every conditional write. */
    public bool $losesWrites = false;

    public function __construct(
        private readonly CallJournal $journal = new CallJournal(),
    ) {
    }

    #[Override]
    public function find(FlagName $name): ?FlagDefinition
    {
        return ($this->definitions[$name->value] ?? null)?->definition;
    }

    #[Override]
    public function all(): array
    {
        return array_values(array_map(static fn (RevisedDefinition $revised): FlagDefinition => $revised->definition, $this->definitions));
    }

    #[Override]
    public function findRevised(FlagName $flag): ?RevisedDefinition
    {
        $this->journal->add('read');

        return $this->definitions[$flag->value] ?? null;
    }

    #[Override]
    public function create(FlagDefinition $definition): bool
    {
        $this->journal->add('write');

        if (isset($this->definitions[$definition->name->value])) {
            return false;
        }

        $this->definitions[$definition->name->value] = new RevisedDefinition($definition, 1);

        return true;
    }

    #[Override]
    public function replace(FlagDefinition $definition, int $revision): bool
    {
        $this->journal->add('write');
        $current = $this->definitions[$definition->name->value] ?? null;

        if ($this->losesWrites || $current?->revision !== $revision) {
            return false;
        }

        $this->definitions[$definition->name->value] = new RevisedDefinition($definition, $revision + 1);

        return true;
    }
}
