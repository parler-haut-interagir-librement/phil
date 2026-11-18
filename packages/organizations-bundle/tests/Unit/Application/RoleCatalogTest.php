<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Application\Permission;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleCatalog::class)]
#[CoversClass(UnknownRole::class)]
final class RoleCatalogTest extends TestCase
{
    public function testTheBuiltInRolesOfAnOrganization(): void
    {
        $roles = RoleCatalog::organization();

        self::assertSame(['owner', 'admin', 'member'], $roles->names());
        self::assertSame(['members.invite', 'members.manage', 'organization.act_on_behalf', 'organization.manage', 'organization.view', 'teams.manage'], $roles->permissionsOf(Roles::of(['admin'])));
        self::assertSame(['organization.view'], $roles->permissionsOf(Roles::of(['member'])));
        self::assertSame(['members.invite', 'members.manage', 'organization.act_on_behalf', 'organization.archive', 'organization.manage', 'organization.view', 'owners.manage', 'teams.manage'], $roles->permissionsOf(Roles::of(['owner'])));
        self::assertSame($roles->permissionsOf(Roles::of(['owner'])), $roles->allPermissions());
    }

    public function testDeclaredRolesAndPermissions(): void
    {
        $roles = RoleCatalog::organization(['billing' => ['billing.read', 'billing.pay', 'billing.read'], 'member' => ['wiki.read'], 'viewer' => []]);

        self::assertSame(['owner', 'admin', 'member', 'billing', 'viewer'], $roles->names());
        self::assertSame(['billing.pay', 'billing.read'], $roles->permissionsOf(Roles::of(['billing'])));
        self::assertSame(['organization.view', 'wiki.read'], $roles->permissionsOf(Roles::of(['member'])));
        self::assertSame([], $roles->permissionsOf(Roles::of(['viewer'])));
        self::assertTrue($roles->grants(Roles::of(['owner']), 'billing.pay'));
        self::assertTrue($roles->grants(Roles::of(['owner']), 'wiki.read'));
        self::assertFalse($roles->grants(Roles::of(['admin']), 'billing.pay'));
        self::assertSame(['billing.pay', 'billing.read', 'organization.view', 'wiki.read'], $roles->permissionsOf(Roles::of(['billing', 'member'])));
        self::assertTrue($roles->has('viewer'));
        self::assertFalse($roles->has('ghost'));
    }

    public function testAnUndeclaredRoleGrantsNothingAndIsRefusedByTheCheck(): void
    {
        $roles = RoleCatalog::organization();

        self::assertSame([], $roles->permissionsOf(Roles::of(['ghost'])));
        self::assertSame(['member'], $roles->check(Roles::of(['member']))->names);

        $this->expectException(UnknownRole::class);
        $this->expectExceptionMessageIsOrContains('The role "ghost" is not declared.');

        $roles->check(Roles::of(['member', 'ghost']));
    }

    public function testCovers(): void
    {
        $roles = RoleCatalog::organization(['billing' => ['billing.read']]);

        self::assertTrue($roles->covers(Roles::of(['owner']), Roles::of(['owner'])));
        self::assertTrue($roles->covers(Roles::of(['admin']), Roles::of(['admin', 'member'])));
        self::assertFalse($roles->covers(Roles::of(['admin']), Roles::of(['owner'])));
        self::assertFalse($roles->covers(Roles::of(['admin']), Roles::of(['billing'])));
        self::assertTrue($roles->covers(Roles::of(['admin', 'billing']), Roles::of(['billing'])));
        self::assertFalse($roles->covers(Roles::of(['member']), Roles::of(['admin'])));
    }

    public function testTheBuiltInRolesOfATeam(): void
    {
        $roles = RoleCatalog::team(['reviewer' => ['review.approve']]);

        self::assertSame(['maintainer', 'member', 'reviewer'], $roles->names());
        self::assertSame([Permission::TEAM_MANAGE], $roles->permissionsOf(Roles::of(['maintainer'])));
        self::assertSame([], $roles->permissionsOf(Roles::of(['member'])));
        self::assertSame(['review.approve', 'team.manage'], $roles->allPermissions());
        self::assertFalse($roles->covers(Roles::of(['maintainer']), Roles::of(['reviewer'])));
    }

    public function testADeclaredRoleOutOfShape(): void
    {
        foreach ([['Billing' => []], [0 => []]] as $declared) {
            try {
                RoleCatalog::organization($declared);
                self::fail('A bad role was taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertStringStartsWith('A role is 1 to 64 lowercase letters, digits or "_", starting with a letter;', $invalidOrganization->getMessage());
            }
        }
    }

    public function testPermissionsAreAList(): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('The permissions of the role "billing" are a list.');

        RoleCatalog::organization(['billing' => 'billing.read']);
    }

    public function testAPermissionOutOfShape(): void
    {
        foreach (['Billing', '1billing', 'billing read', str_repeat('a', 129), 12] as $permission) {
            try {
                RoleCatalog::team(['billing' => [$permission]]);
                self::fail('A bad permission was taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame('A permission is 1 to 128 lowercase letters, digits, "_", "." or "-", starting with a letter; the role "billing" lists one that is not.', $invalidOrganization->getMessage());
            }
        }

        self::assertSame([str_repeat('a', 128)], RoleCatalog::team(['billing' => [str_repeat('a', 128)]])->permissionsOf(Roles::of(['billing'])));
    }

    public function testThePermissionsOfSeveralRolesAreSortedTogether(): void
    {
        $catalog = RoleCatalog::organization(['alpha' => ['z.last'], 'bravo' => ['a.first']]);

        self::assertSame(['a.first', 'z.last'], $catalog->permissionsOf(Roles::of(['alpha', 'bravo'])));
    }
}
