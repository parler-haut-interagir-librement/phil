<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Override;
use Phil\ContentBundle\Domain\Draft\WorkingCopyId;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * The column of a working copy's identifier.
 *
 * @extends IdentifierType<WorkingCopyId>
 */
final class WorkingCopyIdType extends IdentifierType
{
    public const string NAME = 'phil_content_working_copy_id';

    #[Override]
    protected function identifierClass(): string
    {
        return WorkingCopyId::class;
    }
}
