<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Override;

/**
 * A naming strategy of an application's own, whose join columns would not be named `root_id`.
 */
final class ForeignKeyPrefixNamingStrategy extends UnderscoreNamingStrategy
{
    #[Override]
    public function joinColumnName(string $propertyName, string $className): string
    {
        return 'fk_'.$propertyName;
    }
}
