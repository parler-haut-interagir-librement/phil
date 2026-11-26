<?php

declare(strict_types=1);

namespace {{namespace}};

use {{identifier_fqcn}};
use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * The DBAL type of {{identifier}}, registered as "{{type_name}}" in config/packages/doctrine.php.
 *
 * @extends IdentifierType<{{identifier}}>
 */
final class {{class}} extends IdentifierType
{
    #[Override]
    protected function identifierClass(): string
    {
        return {{identifier}}::class;
    }
}
