<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Console;

use LogicException;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacedTenants;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationTarget;

/**
 * The databases to migrate: the shared one, then each dedicated database once, with the first of its tenants.
 */
final readonly class MigrationTargets
{
    public function __construct(
        private TenantPlacement $placement,
    ) {
    }

    /**
     * @param list<string> $only `shared` or database names, to migrate those alone; every database when empty
     *
     * @return list<MigrationTarget>
     *
     * @throws LogicException when the placement lists no tenants, or a name of `$only` is not a database to migrate
     */
    public function targets(array $only = []): array
    {
        if (!$this->placement instanceof PlacedTenants) {
            throw new LogicException('The TenantPlacement service does not implement PlacedTenants: the dedicated databases cannot be listed.');
        }

        $targets = ['shared' => MigrationTarget::shared()];
        $dedicated = [];

        foreach ($this->placement->placedTenants() as $tenant) {
            $placement = $this->placement->placementOf($tenant);

            if (null !== $placement->database && !isset($dedicated[$placement->database])) {
                $dedicated[$placement->database] = new MigrationTarget($placement, $tenant);
            }
        }

        ksort($dedicated);
        $targets = [...$targets, ...$dedicated];

        if ([] === $only) {
            return array_values($targets);
        }

        $selected = [];

        foreach ($only as $name) {
            $selected[$name] = $targets[$name] ?? throw new LogicException(\sprintf('"%s" is neither "shared" nor the database of a placed tenant.', $name));
        }

        return array_values($selected);
    }
}
