<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Application;

use Override;
use Phil\AccessBundle\Application\AccessModel;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\RewriteKind;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessModel::class)]
final class AccessModelTest extends TestCase
{
    public function testTheKindsAndRelations(): void
    {
        $model = AccessModel::create(['page'], SharingLevels::of(SharingLevels::DEFAULT));

        self::assertSame(['member', 'visitor', 'visibility', 'group', 'space', 'page'], $model->kinds());
        self::assertSame(['owner', 'space', 'visibility', 'manage', 'edit', 'comment', 'view'], array_keys($model->type('page')->relations));
        self::assertSame('([member, member:*, visitor:*, group#member, space#member, space#admin] or owner)', $model->rewrite('page', 'manage')->toString());
        self::assertSame('([member, member:*, visitor:*, group#member, space#member, space#admin] or comment)', $model->rewrite('page', 'view')->toString());
        self::assertSame('([member, group#member] or admin)', $model->rewrite('space', 'member')->toString());
        self::assertSame(RewriteKind::Direct, $model->rewrite('space', 'admin')->kind);
        self::assertTrue($model->accepts('group', 'member', Subject::fromString('member:a')));
        self::assertFalse($model->accepts('group', 'member', Subject::fromString('member:*')));
        self::assertTrue($model->accepts('page', 'owner', Subject::fromString('member:a')));
        self::assertTrue($model->accepts('page', 'space', Subject::fromString('space:main')));
        self::assertTrue($model->accepts('page', 'visibility', Subject::fromString('visibility:members')));
        self::assertTrue($model->accepts('space', 'admin', Subject::fromString('group:g#member')));
    }

    public function testAReservedKindIsRefused(): void
    {
        foreach (['member', 'visitor', 'visibility', 'space', 'group'] as $kind) {
            try {
                AccessModel::create([$kind], SharingLevels::of(['view']));
                self::fail(\sprintf('"%s" should be refused.', $kind));
            } catch (InvalidAccess $invalid) {
                self::assertSame(\sprintf('"%s" cannot be a kind of item: it is reserved, or listed twice.', $kind), $invalid->getMessage());
            }
        }

        $this->expectException(InvalidAccess::class);

        AccessModel::create(['page', 'page'], SharingLevels::of(['view']));
    }

    public function testContributorsAddKindsAndRelations(): void
    {
        $folders = new class implements ModelContributor {
            #[Override]
            public function contribute(array $types): array
            {
                $types['folder'] = TypeDefinition::of('folder', ['viewer' => Rewrite::direct('member')]);
                $types['page'] = $types['page']->with(['parent' => Rewrite::direct('folder')]);

                return $types;
            }
        };

        $model = AccessModel::create(['page'], SharingLevels::of(['view']), [$folders]);

        self::assertTrue($model->hasRelation('folder', 'viewer'));
        self::assertTrue($model->hasRelation('page', 'parent'));
    }
}
