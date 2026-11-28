<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Relation;

use Phil\RelatedContent\Exception\InvalidItem;
use Phil\RelatedContent\Relation\RelationType;
use Phil\RelatedContent\Relation\TypedRelation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypedRelation::class)]
final class TypedRelationTest extends TestCase
{
    public function testARelationKeepsItsEnds(): void
    {
        $relation = new TypedRelation('product:game', 'product:sleeves', RelationType::Accessory);

        self::assertSame('product:game', $relation->from);
        self::assertSame('product:sleeves', $relation->to);
        self::assertSame(RelationType::Accessory, $relation->type);
    }

    public function testTheSourceIsValidated(): void
    {
        $this->expectException(InvalidItem::class);

        new TypedRelation('', 'b', RelationType::Upgrade);
    }

    public function testTheTargetIsValidated(): void
    {
        $this->expectException(InvalidItem::class);

        new TypedRelation('a', "b\n", RelationType::Upgrade);
    }
}
