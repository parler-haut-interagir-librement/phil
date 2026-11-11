<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * The subclass an application writes for each identifier, reduced to its one method.
 *
 * @extends IdentifierType<ExampleId>
 */
final class ExampleIdType extends IdentifierType
{
    #[Override]
    protected function identifierClass(): string
    {
        return ExampleId::class;
    }
}
