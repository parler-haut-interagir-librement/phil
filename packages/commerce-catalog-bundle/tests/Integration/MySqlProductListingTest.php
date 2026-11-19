<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Integration;

use Override;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DbalProductListing;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\ProductListingTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DbalProductListing::class)]
#[Group('mysql')]
final class MySqlProductListingTest extends ProductListingTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
