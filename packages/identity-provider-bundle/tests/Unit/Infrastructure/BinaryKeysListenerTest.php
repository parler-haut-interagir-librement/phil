<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([
                'phil_identity_account' => ['email', 'email_canonical', 'id'],
                'phil_identity_address' => ['account_id', 'canonical', 'id'],
                'phil_identity_assertion' => ['id'],
                'phil_identity_client' => ['id'],
                'phil_identity_code' => ['account_id', 'client_id', 'family', 'id', 'session_id'],
                'phil_identity_consent' => ['account_id', 'client_id', 'id'],
                'phil_identity_link_token' => ['account_id', 'id'],
                'phil_identity_linked' => ['account_id', 'id', 'provider', 'subject'],
                'phil_identity_passkey' => ['account_id', 'id'],
                'phil_identity_recovery_codes' => ['account_id'],
                'phil_identity_revoked_family' => ['family'],
                'phil_identity_session' => ['account_id', 'id'],
                'phil_identity_signing_key' => ['kid'],
                'phil_identity_token' => ['account_id', 'client_id', 'family', 'id', 'session_id'],
                'phil_identity_totp' => ['account_id'],
            ], $this->binaryColumns($this->schema(['driver' => 'pdo_mysql', 'serverVersion' => $version])), $version);
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ($this->schema(['driver' => 'pdo_sqlite', 'memory' => true])->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }
        }

        $metadata = $this->entityManager(['driver' => 'pdo_pgsql', 'serverVersion' => '17.0'])->getClassMetadata(Account::class);
        self::assertNull($metadata->getFieldMapping('id')->options);
        self::assertSame(['default' => 'active'], $metadata->getFieldMapping('state')->options);
    }

    public function testTheOtherOptionsOfAColumnAreKept(): void
    {
        $entityManager = $this->entityManager(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $metadata = $entityManager->getClassMetadata(Account::class);

        self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('id')->options);
        self::assertSame(['default' => 'active'], $metadata->getFieldMapping('state')->options);
        self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('email')->options);
        self::assertNull($metadata->getFieldMapping('displayName')->options);
    }

    public function testTheOptionsAKeyAlreadyHasAreKeptNextToTheCollation(): void
    {
        $metadata = $this->entityManager(['driver' => 'pdo_sqlite', 'memory' => true])->getClassMetadata(Account::class);
        $metadata->getFieldMapping('id')->options = ['comment' => 'the key'];

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entityManager(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0'])));

        self::assertSame(['comment' => 'the key', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('id')->options);
    }

    public function testAnotherEntityIsLeftAsItIs(): void
    {
        $metadata = new ClassMetadata(stdClass::class);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'string']);

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entityManager(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0'])));

        self::assertNull($metadata->getFieldMapping('id')->options);
    }

    /**
     * @param array{driver: 'pdo_mysql'|'pdo_pgsql'|'pdo_sqlite', serverVersion?: string, memory?: bool} $connection
     */
    private function entityManager(array $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection($connection, $configuration), $configuration);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return $entityManager;
    }

    /**
     * @param array{driver: 'pdo_mysql'|'pdo_sqlite', serverVersion?: string, memory?: bool} $connection
     */
    private function schema(array $connection): Schema
    {
        $entityManager = $this->entityManager($connection);

        return new SchemaTool($entityManager)->getSchemaFromMetadata($entityManager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(Schema $schema): array
    {
        $binary = [];

        foreach ($schema->getTables() as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                if ('utf8mb4_bin' === $column->getCollation()) {
                    self::assertSame('utf8mb4', $column->getCharset());
                    $columns[] = $column->getObjectName()->toString();
                }
            }

            sort($columns);
            $binary[$table->getObjectName()->toString()] = $columns;
        }

        ksort($binary);

        return $binary;
    }
}
