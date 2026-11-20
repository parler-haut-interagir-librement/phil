<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;

/**
 * What a page pays on the tree of units, on the DBAL store over SQLite in memory: the permissions a member inherits in
 * a unit four levels deep, the units below a unit of the second level, its members, and a move of that unit with the
 * 73 units below it. The tree holds 10 roots of 8 children each, four levels deep: 5 850 units; the comparison with a
 * closure table on MySQL and PostgreSQL is in `doc/units.md`.
 */
final readonly class UnitTreeBench
{
    private Stage $stage;

    private Unit $deep;

    private Unit $branch;

    private Unit $root;

    private Unit $otherRoot;

    public function __construct()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $store = new DbalOrganizationStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $this->stage = new Stage($store);
        $acme = $this->stage->acme();
        $roots = [];

        $connection->beginTransaction();

        for ($r = 0; $r < 10; ++$r) {
            $roots[] = $this->grow($acme, null, 1);
        }

        $connection->commit();
        $this->root = $roots[1];
        $this->otherRoot = $roots[2];
        $this->branch = $this->stage->tree->children($this->root->id)[3];
        $this->deep = $this->stage->tree->descendants($this->branch->id)[20];
        $this->stage->units->addMember($this->branch->id, Stage::member('ann'), Stage::member('cam'), ['manager']);
    }

    public function benchInheritedPermissions(): void
    {
        $this->stage->unitAccess->permissions($this->deep, Stage::member('cam'));
    }

    public function benchDescendants(): void
    {
        $this->stage->tree->descendants($this->branch->id);
    }

    public function benchMembersWithin(): void
    {
        $this->stage->tree->membersWithin($this->root->id);
    }

    public function benchMoveASubtree(): void
    {
        $this->stage->units->move($this->branch->id, Stage::member('ann'), $this->otherRoot->id);
        $this->stage->units->move($this->branch->id, Stage::member('ann'), $this->root->id);
    }

    private function grow(Organization $organization, ?Unit $parent, int $depth): Unit
    {
        $unit = $this->stage->units->create($organization->id, Stage::member('ann'), 'Unit', $parent?->id);

        if ($depth < 4) {
            for ($i = 0; $i < 8; ++$i) {
                $this->grow($organization, $unit, $depth + 1);
            }
        }

        return $unit;
    }
}
