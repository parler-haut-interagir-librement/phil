<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\Relation\TypedRelation;

/**
 * The relations a merchant set between products: accessory, complement, upgrade.
 */
interface TypedRelations
{
    /**
     * The relations that start from this item, in the order they are shown.
     *
     * @return list<TypedRelation>
     */
    public function from(string $id): array;

    /**
     * The relations that end at this item; the engine reads their complements, which hold both ways.
     *
     * @return list<TypedRelation>
     */
    public function to(string $id): array;
}
