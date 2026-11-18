<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Model\LeasedResource;
use Phil\EditLockBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheKeysCompareByteForByteOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            $manager = $this->manager(['driver' => 'pdo_mysql', 'serverVersion' => $version]);
            $lease = $manager->getClassMetadata(Lease::class);

            foreach (['resource', 'part', 'holderId'] as $field) {
                self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $lease->getFieldMapping($field)->options, $field);
            }

            self::assertNull($lease->getFieldMapping('holderName')->options);
            self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $manager->getClassMetadata(LeasedResource::class)->getFieldMapping('resource')->options);
        }
    }

    public function testTheOtherPlatformsKeepTheirMapping(): void
    {
        foreach ([['driver' => 'pdo_pgsql', 'serverVersion' => '17'], ['driver' => 'pdo_sqlite', 'memory' => true]] as $params) {
            $manager = $this->manager($params);

            self::assertNull($manager->getClassMetadata(Lease::class)->getFieldMapping('resource')->options);
            self::assertNull($manager->getClassMetadata(LeasedResource::class)->getFieldMapping('resource')->options);
        }
    }

    /**
     * @param array{driver: 'pdo_mysql'|'pdo_pgsql'|'pdo_sqlite', serverVersion?: string, memory?: bool} $params
     */
    private function manager(array $params): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model', __DIR__], true);
        $config->enableNativeLazyObjects(true);

        $manager = new EntityManager(DriverManager::getConnection($params), $config);
        $manager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return $manager;
    }
}
