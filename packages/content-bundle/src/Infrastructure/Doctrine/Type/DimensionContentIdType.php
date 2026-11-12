<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Override;
use Phil\ContentBundle\Domain\Content\DimensionContentId;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * The identifier of a dimension row: a native `uuid` column where the platform has one, `BINARY(16)`
 * elsewhere, as every `IdentifierType`.
 *
 * @extends IdentifierType<DimensionContentId>
 */
final class DimensionContentIdType extends IdentifierType
{
    public const string NAME = 'phil_content_dimension_content_id';

    #[Override]
    protected function identifierClass(): string
    {
        return DimensionContentId::class;
    }
}
