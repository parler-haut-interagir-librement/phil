<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\AccessBundle\Application\AccessModel;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\OrganizationsBundle\Infrastructure\Access\MembershipTuples;
use Phil\OrganizationsBundle\Infrastructure\Access\OrganizationRelations;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What a change of roles writes: only the tuples of the roles taken away are deleted, only those of the roles given
 * are written, each as a list.
 */
#[CoversClass(MembershipTuples::class)]
final class TupleChangesTest extends TestCase
{
    public function testAChangeOfOrganizationRolesDeletesWhatWasTakenAndWritesWhatWasGiven(): void
    {
        $update = $this->update(static fn (MembershipTuples $tuples) => $tuples->onMemberRolesChanged(new MemberRolesChanged('o1', 'bob', ['member', 'admin', 'billing'], ['member', 'owner'], 'ann')));

        self::assertEquals([$this->tuple('organization', 'o1', 'admin', 'bob'), $this->tuple('organization', 'o1', 'billing', 'bob')], $update[0]);
        self::assertEquals([$this->tuple('organization', 'o1', 'owner', 'bob')], $update[1]);
        self::assertSame(range(0, \count($update[0]) - 1), array_keys($update[0]));
        self::assertSame(range(0, \count($update[1]) - 1), array_keys($update[1]));
    }

    public function testAChangeOfTeamRolesDeletesWhatWasTakenAndWritesWhatWasGiven(): void
    {
        $update = $this->update(static fn (MembershipTuples $tuples) => $tuples->onTeamMemberRolesChanged(new TeamMemberRolesChanged('o1', 't1', 'cam', ['member', 'reviewer'], ['member', 'maintainer'], 'dan')));

        self::assertEquals([$this->tuple('team', 't1', 'reviewer', 'cam')], $update[0]);
        self::assertEquals([$this->tuple('team', 't1', 'maintainer', 'cam')], $update[1]);
        self::assertSame(range(0, \count($update[0]) - 1), array_keys($update[0]));
        self::assertSame(range(0, \count($update[1]) - 1), array_keys($update[1]));
    }

    public function testRolesKeptAreNeitherDeletedNorWrittenAgain(): void
    {
        $update = $this->update(static fn (MembershipTuples $tuples) => $tuples->onMemberRolesChanged(new MemberRolesChanged('o1', 'bob', ['member'], ['member'], 'ann')));

        self::assertSame([[], []], $update);
    }

    /**
     * @param callable(MembershipTuples): mixed $change
     *
     * @return array{list<RelationTuple>, list<RelationTuple>}
     */
    private function update(callable $change): array
    {
        $organization = RoleCatalog::organization(['billing' => ['billing.read']]);
        $team = RoleCatalog::team(['reviewer' => []]);
        $relations = new OrganizationRelations($organization, $team);
        $model = AccessModel::create(['page'], SharingLevels::of(['edit', 'view']), [$relations]);
        $calls = [];
        $store = self::createStub(TupleStore::class);
        $store->method('update')->willReturnCallback(static function (array $delete, array $write) use (&$calls): void {
            $calls[] = [$delete, $write];
        });

        $change(new MembershipTuples(new RelationWriter($model, $store), $relations));

        self::assertCount(1, $calls);

        /** @var array{list<RelationTuple>, list<RelationTuple>} $update */
        $update = $calls[0];

        return $update;
    }

    private function tuple(string $kind, string $id, string $relation, string $member): RelationTuple
    {
        return new RelationTuple(ResourceReference::of($kind, $id), $relation, Subject::of(ResourceReference::of(OrganizationRelations::MEMBER_KIND, $member)));
    }
}
