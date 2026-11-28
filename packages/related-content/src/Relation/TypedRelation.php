<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Relation;

use Phil\RelatedContent\Exception\InvalidItem;
use Phil\RelatedContent\Item;

/**
 * A relation a merchant set from one product to another.
 *
 * ```php
 * new TypedRelation('product:game', 'product:sleeves', RelationType::Accessory);
 * ```
 */
final readonly class TypedRelation
{
    /**
     * @throws InvalidItem when an identifier is malformed
     */
    public function __construct(
        public string $from,
        public string $to,
        public RelationType $type,
    ) {
        Item::assertIdentifier($from);
        Item::assertIdentifier($to);
    }
}
