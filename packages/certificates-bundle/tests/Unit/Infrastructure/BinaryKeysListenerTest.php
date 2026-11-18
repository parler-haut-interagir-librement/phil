<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    private const array BINARY = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];

    public function testTheKeysCompareByteForByteOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            $manager = self::manager(['driver' => 'pdo_mysql', 'serverVersion' => $version]);
            $certificate = $manager->getClassMetadata(Certificate::class);
            $course = $manager->getClassMetadata(Course::class);
            $activity = $manager->getClassMetadata(LearningActivity::class);

            self::assertSame(self::BINARY + ['fixed' => true], $certificate->getFieldMapping('codeValue')->options);
            self::assertSame(self::BINARY, $certificate->getFieldMapping('holder')->options);
            self::assertSame(self::BINARY, $certificate->getFieldMapping('completion')->options);
            self::assertSame(['fixed' => true], $certificate->getFieldMapping('id')->options, 'An identifier a foreign key references keeps its collation.');
            self::assertNull($certificate->getFieldMapping('recipientName')->options);
            self::assertSame(self::BINARY, $course->getFieldMapping('slug')->options);
            self::assertSame(self::BINARY, $course->getFieldMapping('completionSource')->options);
            self::assertSame(self::BINARY, $activity->getFieldMapping('member')->options);
            self::assertSame(self::BINARY, $activity->getFieldMapping('source')->options);
        }
    }

    public function testTheOtherPlatformsKeepTheirMapping(): void
    {
        foreach ([['driver' => 'pdo_pgsql', 'serverVersion' => '17'], ['driver' => 'pdo_sqlite', 'memory' => true]] as $params) {
            $manager = self::manager($params);

            self::assertSame(['fixed' => true], $manager->getClassMetadata(Certificate::class)->getFieldMapping('codeValue')->options);
            self::assertNull($manager->getClassMetadata(LearningActivity::class)->getFieldMapping('member')->options);
        }
    }

    /**
     * @param array{driver: 'pdo_mysql'|'pdo_pgsql'|'pdo_sqlite', serverVersion?: string, memory?: bool} $params
     */
    public static function manager(array $params): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $manager = new EntityManager(DriverManager::getConnection($params), $config);
        $manager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return $manager;
    }
}
