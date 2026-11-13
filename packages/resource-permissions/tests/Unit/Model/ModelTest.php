<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Model;

use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnknownRelation;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\RewriteKind;
use Phil\ResourcePermissions\Model\SubjectType;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Model::class)]
#[CoversClass(TypeDefinition::class)]
#[CoversClass(Rewrite::class)]
#[CoversClass(SubjectType::class)]
#[CoversClass(UnknownRelation::class)]
final class ModelTest extends TestCase
{
    public function testAModelOfFoldersAndDocuments(): void
    {
        $model = self::model();

        self::assertSame(['member', 'group', 'folder', 'doc'], $model->kinds());
        self::assertTrue($model->hasRelation('doc', 'viewer'));
        self::assertFalse($model->hasRelation('doc', 'owner'));
        self::assertFalse($model->hasRelation('page', 'viewer'));
        self::assertSame(RewriteKind::Union, $model->rewrite('doc', 'viewer')->kind);
        self::assertSame('doc', $model->type('doc')->kind);
    }

    public function testAccepts(): void
    {
        $model = self::model();

        self::assertTrue($model->accepts('doc', 'viewer', Subject::fromString('member:ann')));
        self::assertTrue($model->accepts('doc', 'viewer', Subject::fromString('member:*')));
        self::assertTrue($model->accepts('doc', 'viewer', Subject::fromString('group:g#member')));
        self::assertFalse($model->accepts('doc', 'viewer', Subject::fromString('group:g')));
        self::assertFalse($model->accepts('doc', 'viewer', Subject::fromString('group:*')));
        self::assertTrue($model->accepts('doc', 'parent', Subject::fromString('folder:f')));
        self::assertFalse($model->accepts('doc', 'parent', Subject::fromString('doc:f')));
        self::assertFalse($model->accepts('folder', 'viewer', Subject::fromString('member:ann')));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(UnknownRelation::class);
        $this->expectExceptionMessageIsOrContains('The model declares no kind "page".');

        self::model()->rewrite('page', 'viewer');
    }

    public function testAnUnknownRelationIsRefused(): void
    {
        $this->expectException(UnknownRelation::class);
        $this->expectExceptionMessageIsOrContains('The kind "doc" declares no relation "owner".');

        self::model()->accepts('doc', 'owner', Subject::fromString('member:a'));
    }

    /**
     * @param list<TypeDefinition> $types
     */
    #[DataProvider('invalidModels')]
    public function testAnInvalidModelIsRefused(array $types, string $message): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Model(...$types);
    }

    /**
     * @return iterable<string, array{list<TypeDefinition>, string}>
     */
    public static function invalidModels(): iterable
    {
        $member = TypeDefinition::of('member');

        yield 'twice' => [[$member, TypeDefinition::of('member')], 'The kind "member" is declared twice.'];
        yield 'unknown kind' => [[TypeDefinition::of('doc', ['viewer' => Rewrite::direct('member')])], 'doc#viewer names the kind "member", which the model does not declare.'];
        yield 'unknown set' => [[$member, TypeDefinition::of('doc', ['viewer' => Rewrite::direct('member#owner')])], 'doc#viewer accepts member#owner, which the model does not declare.'];
        yield 'unknown every' => [[TypeDefinition::of('doc', ['viewer' => Rewrite::direct('member:*')])], 'names the kind "member"'];
        yield 'unknown computed' => [[$member, TypeDefinition::of('doc', ['viewer' => Rewrite::computed('editor')])], 'doc#viewer reads the relation "editor", which "doc" does not declare.'];
        yield 'nested unknown computed' => [[$member, TypeDefinition::of('doc', ['viewer' => Rewrite::union(Rewrite::direct('member'), Rewrite::computed('editor'))])], 'reads the relation "editor"'];
        yield 'nested in exclusion' => [[$member, TypeDefinition::of('doc', ['viewer' => Rewrite::exclusion(Rewrite::direct('member'), Rewrite::direct('group'))])], 'names the kind "group"'];
        yield 'missing via' => [[$member, TypeDefinition::of('doc', ['viewer' => Rewrite::fromRelated('parent', 'viewer')])], 'doc#viewer follows "parent", which must be a direct relation of "doc".'];
        yield 'computed via' => [[$member, TypeDefinition::of('doc', ['owner' => Rewrite::direct('member'), 'parent' => Rewrite::computed('owner'), 'viewer' => Rewrite::fromRelated('parent', 'viewer')])], 'which must be a direct relation'];
        yield 'nothing to read' => [[$member, TypeDefinition::of('doc', ['parent' => Rewrite::direct('member'), 'viewer' => Rewrite::fromRelated('parent', 'viewer')])], 'doc#viewer reads "viewer" on the objects of "parent", none of which declares it.'];
        yield 'only sets and every to read' => [[TypeDefinition::of('member', ['viewer' => Rewrite::direct('member')]), TypeDefinition::of('doc', ['parent' => Rewrite::direct('member#viewer', 'member:*'), 'viewer' => Rewrite::fromRelated('parent', 'viewer')])], 'none of which declares it'];
        yield 'via an undeclared kind' => [[TypeDefinition::of('doc', ['viewer' => Rewrite::fromRelated('parent', 'viewer'), 'parent' => Rewrite::direct('folder')])], 'none of which declares it'];
    }

    public function testFromRelatedReadsAKindThatDeclaresTheRelation(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('folder', ['viewer' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', ['parent' => Rewrite::direct('member', 'folder'), 'viewer' => Rewrite::fromRelated('parent', 'viewer')]),
        );

        self::assertTrue($model->hasRelation('doc', 'viewer'));
    }

    public function testATypeDefinitionChecksItsNames(): void
    {
        foreach (['Doc', '', '1doc', 'd'.str_repeat('o', 64)] as $kind) {
            try {
                TypeDefinition::of($kind);
                self::fail(\sprintf('"%s" should be refused.', $kind));
            } catch (InvalidRelation $invalid) {
                self::assertStringContainsString('A kind is 1 to 64', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidRelation::class);

        TypeDefinition::of('doc', ['Viewer' => Rewrite::direct('member')]);
    }

    public function testATypeDefinitionGrows(): void
    {
        $type = TypeDefinition::of('doc', ['owner' => Rewrite::direct('member')])->with(['viewer' => Rewrite::computed('owner')]);

        self::assertSame(['owner', 'viewer'], array_keys($type->relations));
        self::assertSame(RewriteKind::Computed, $type->rewrite('viewer')->kind);

        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('The kind "doc" already declares the relation "owner".');

        $type->with(['owner' => Rewrite::direct('member')]);
    }

    public function testRewritesAsText(): void
    {
        $rewrite = Rewrite::union(
            Rewrite::exclusion(Rewrite::direct('member', 'member:*', 'group#member'), Rewrite::computed('blocked')),
            Rewrite::intersection(Rewrite::computed('a'), Rewrite::fromRelated('parent', 'viewer')),
            Rewrite::computed('owner'),
        );

        self::assertSame('(([member, member:*, group#member] but not blocked) or (a and viewer from parent) or owner)', $rewrite->toString());
        self::assertSame(['member', 'member:*', 'group#member'], array_map(static fn (SubjectType $type): string => $type->toString(), $rewrite->directTypes()));
        self::assertSame([], Rewrite::computed('a')->directTypes());
    }

    public function testSubjectTypes(): void
    {
        $every = SubjectType::fromString('member:*');
        $set = SubjectType::fromString('group#member');
        $one = SubjectType::fromString('member');

        self::assertTrue($every->every);
        self::assertNull($every->relation);
        self::assertSame('member', $every->kind);
        self::assertSame('member', $set->relation);
        self::assertFalse($set->every);
        self::assertTrue($one->accepts(Subject::fromString('member:a')));
        self::assertFalse($one->accepts(Subject::fromString('member:*')));
        self::assertFalse($one->accepts(Subject::fromString('member:a#owner')));
        self::assertFalse($one->accepts(Subject::fromString('group:a')));
        self::assertTrue($every->accepts(Subject::fromString('member:*')));
        self::assertFalse($every->accepts(Subject::fromString('member:a')));
        self::assertTrue($set->accepts(Subject::fromString('group:g#member')));
        self::assertFalse($set->accepts(Subject::fromString('group:g#owner')));

        foreach (['Member', 'member#', 'member#Owner', ':*', 'member:x', 'a b'] as $refused) {
            try {
                SubjectType::fromString($refused);
                self::fail(\sprintf('"%s" should be refused.', $refused));
            } catch (InvalidRelation) {
                self::addToAssertionCount(1);
            }
        }
    }

    public static function model(): Model
    {
        return new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'group#member')]),
            TypeDefinition::of('folder', [
                'parent' => Rewrite::direct('folder'),
                'viewer' => Rewrite::union(Rewrite::computed('editor'), Rewrite::fromRelated('parent', 'viewer')),
                'editor' => Rewrite::direct('group#member'),
            ]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('folder'),
                'viewer' => Rewrite::union(Rewrite::direct('member', 'member:*', 'group#member'), Rewrite::fromRelated('parent', 'viewer')),
            ]),
        );
    }
}
