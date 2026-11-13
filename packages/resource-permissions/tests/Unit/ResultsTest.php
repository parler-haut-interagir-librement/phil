<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Explanation;
use Phil\ResourcePermissions\ObjectList;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Explanation::class)]
#[CoversClass(ObjectList::class)]
final class ResultsTest extends TestCase
{
    public function testAnExplanationReadsAsLines(): void
    {
        $explanation = new Explanation([
            new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('group:g#member')),
            new RelationTuple(ResourceReference::of('group', 'g'), 'member', Subject::fromString('member:ann')),
        ]);

        self::assertSame(['doc:1#viewer@group:g#member', 'group:g#member@member:ann'], $explanation->lines());
    }

    public function testAnObjectListGivesItsIdentifiers(): void
    {
        $list = new ObjectList([ResourceReference::of('doc', '1'), ResourceReference::of('doc', 'b')], true);

        self::assertSame(['1', 'b'], $list->ids());
        self::assertTrue($list->complete);
    }
}
