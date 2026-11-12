<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * The surrogate identifier of a stored dimension row, generated when the row is built.
 *
 * Nothing outside the storage addresses a row by it: a row is found by its root and its dimension, which a
 * unique constraint keeps to one row. The identifier exists because an entity needs a primary key, and a
 * composite key made of the root and three value objects would turn every identity-map lookup into a
 * conversion of each of them.
 */
final readonly class DimensionContentId extends Identifier
{
}
