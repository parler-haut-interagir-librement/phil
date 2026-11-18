<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CertificatesBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The register's keys on a real server: two members, or two sources, differing by their case are two.
 *
 *     PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil' \
 *     PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil' \
 *     vendor/bin/phpunit packages/certificates-bundle/tests/Integration/BinaryKeysTest.php
 */
#[CoversClass(BinaryKeysListener::class)]
#[Group('mysql')]
#[Group('postgresql')]
final class BinaryKeysTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function servers(): iterable
    {
        yield 'MySQL' => ['PHIL_TEST_MYSQL_URL'];
        yield 'PostgreSQL' => ['PHIL_TEST_POSTGRESQL_URL'];
    }

    #[DataProvider('servers')]
    public function testMembersAndSourcesThatDifferByTheirCaseAreKeptApart(string $variable): void
    {
        $url = getenv($variable);

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $variable));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($url));
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $manager = new EntityManager($connection, $config);
        $manager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        $schema = new SchemaTool($manager);
        $classes = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($classes);
        $schema->createSchema($classes);

        try {
            foreach ([['a', 'Alice'], ['b', 'alice']] as [$id, $member]) {
                $connection->insert('phil_certificates_activity', ['id' => str_pad($id, 36, '0'), 'member' => $member, 'title' => 'T', 'completed_at' => '2026-11-17 00:00:00', 'credits' => 100, 'source' => 'Workshop-1']);
            }

            self::assertSame(['Alice'], $connection->fetchFirstColumn('SELECT member FROM phil_certificates_activity WHERE member = ?', ['Alice']));
            self::assertSame(['alice'], $connection->fetchFirstColumn('SELECT member FROM phil_certificates_activity WHERE member = ?', ['alice']));
        } finally {
            $schema->dropSchema($classes);
        }
    }
}
