<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Integration;

use Override;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\PriceBookOrderMigration;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\PriceBookMigrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(PriceBookOrderMigration::class)]
#[Group('postgresql')]
final class PostgreSqlPriceBookOrderMigrationTest extends PriceBookMigrationTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
