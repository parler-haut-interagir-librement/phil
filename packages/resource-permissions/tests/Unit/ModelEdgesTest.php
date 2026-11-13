<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\SubjectType;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Traversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the small value classes: names that end in a line break, lists given with keys, text split across two
 * identifiers.
 */
#[CoversClass(Rewrite::class)]
#[CoversClass(SubjectType::class)]
#[CoversClass(TypeDefinition::class)]
#[CoversClass(RelationTuple::class)]
#[CoversClass(Traversal::class)]
final class ModelEdgesTest extends TestCase
{
    public function testAKindEndingWithALineBreakIsRefused(): void
    {
        $this->expectException(InvalidRelation::class);

        TypeDefinition::of("doc\n");
    }

    public function testASubjectTypeEndingWithALineBreakIsRefused(): void
    {
        $this->expectException(InvalidRelation::class);

        SubjectType::fromString("member\n");
    }

    public function testASubjectTypeWithTwoHashesIsRefusedAsARelationName(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIs('A relation is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "member#x" is not.');

        SubjectType::fromString('group#member#x');
    }

    public function testOperandsGivenWithKeysStayAList(): void
    {
        $union = Rewrite::union(Rewrite::computed('a'), Rewrite::computed('b'), ...['x' => Rewrite::computed('c')]);
        $intersection = Rewrite::intersection(Rewrite::computed('a'), Rewrite::computed('b'), ...['x' => Rewrite::computed('c')]);

        self::assertSame([0, 1, 2], array_keys(Rewrite::direct('member', ...['a' => 'team', 'b' => 'group#member'])->types));
        self::assertSame([0, 1, 2], array_keys($union->children));
        self::assertSame([0, 1, 2], array_keys($intersection->children));
    }

    public function testTheIdentifiersOfATupleAreCheckedOneByOne(): void
    {
        $this->expectException(InvalidRelation::class);

        new RelationTuple(ResourceReference::of('doc', "a\xC3"), 'viewer', Subject::of(ResourceReference::of('member', "\xA9b")));
    }

    public function testSubjectIdentifiersAreCheckedAsWell(): void
    {
        $this->expectException(InvalidRelation::class);

        new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::of(ResourceReference::of('member', "\xA9")));
    }

    public function testWhatIsGatheredForAStepIsKeptAcrossRounds(): void
    {
        $traversal = new Traversal(new Budget());
        $first = ResourceReference::of('doc', '1');
        $second = ResourceReference::of('doc', '2');

        $traversal->keepGathered('doc#viewer', ['doc:1' => $first]);
        $traversal->keepGathered('doc#viewer', ['doc:2' => $second]);

        self::assertSame(['doc:1' => $first, 'doc:2' => $second], $traversal->gathered('doc#viewer'));
        self::assertSame(2, $traversal->gatheredCount());
    }
}
