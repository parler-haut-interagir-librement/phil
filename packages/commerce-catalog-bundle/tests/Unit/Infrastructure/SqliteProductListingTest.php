<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Application\ProductPage;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DbalProductListing;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\ProductListingTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalProductListing::class)]
#[CoversClass(ListedProduct::class)]
#[CoversClass(ProductPage::class)]
final class SqliteProductListingTest extends ProductListingTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }
}
