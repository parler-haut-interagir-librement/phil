<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Infrastructure;

use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryProductionStore;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryProductionStore::class)]
final class DirectoryProductionStoreTest extends TestCase
{
    public function testItsFilesAreReadableByTheirOwnerOnly(): void
    {
        $directory = sys_get_temp_dir().'/phil-product-template-production-'.sodium_bin2hex(random_bytes(4));
        $store = new DirectoryProductionStore($directory);

        self::assertFalse($store->has('production-orders/R-1.json'));
        self::assertNull($store->read('production-orders/R-1.json'));

        $store->write('production-orders/R-1.json', '{}');

        self::assertTrue($store->has('production-orders/R-1.json'));
        self::assertSame('{}', $store->read('production-orders/R-1.json'));
        self::assertSame(0o700, fileperms($directory.'/production-orders') & 0o777);
        self::assertSame(0o600, fileperms($directory.'/production-orders/R-1.json') & 0o777);

        unlink($directory.'/production-orders/R-1.json');
        rmdir($directory.'/production-orders');
        rmdir($directory);

        $this->expectExceptionObject(InvalidValue::because('"../R-1.json" is not a path of the store of images.'));

        $store->has('../R-1.json');
    }
}
