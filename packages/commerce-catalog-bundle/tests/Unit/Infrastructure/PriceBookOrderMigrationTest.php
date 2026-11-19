<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\PriceBookOrderMigration;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\PriceBookMigrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PriceBookOrderMigration::class)]
final class PriceBookOrderMigrationTest extends PriceBookMigrationTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }
}
