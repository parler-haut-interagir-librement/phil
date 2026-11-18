<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Integration;

use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalInvitationStore::class)]
final class MySqlInvitationStoreTest extends ServerInvitationStoreTestCase
{
    public function testTheIdentifiersAreBinaryOnTheServerAndAnEarlierTableIsAlteredByTheDiff(): void
    {
        $connection = $this->connection();
        $this->store();
        $binary = static fn (string $table): array => $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
            [$table],
        );

        self::assertSame(['campaign', 'id', 'recipient'], $binary(DbalInvitationStore::INVITATION_TABLE));
        self::assertSame(['hash', 'invitation_id'], $binary(DbalInvitationStore::LINK_TABLE));

        $connection->executeStatement('ALTER TABLE '.DbalInvitationStore::INVITATION_TABLE.' MODIFY campaign VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL, MODIFY recipient VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL');

        self::assertSame(['id'], $binary(DbalInvitationStore::INVITATION_TABLE));

        $manager = $connection->createSchemaManager();
        $target = new DbalInvitationStore($connection)->configureSchema(Schema::editor()->create())->getTable(DbalInvitationStore::INVITATION_TABLE);
        $diff = $manager->createComparator()->compareTables($manager->introspectTableByUnquotedName(DbalInvitationStore::INVITATION_TABLE), $target);

        self::assertFalse($diff->isEmpty());

        $manager->alterTable($diff);

        self::assertSame(['campaign', 'id', 'recipient'], $binary(DbalInvitationStore::INVITATION_TABLE));
        self::assertTrue($manager->createComparator()->compareTables($manager->introspectTableByUnquotedName(DbalInvitationStore::INVITATION_TABLE), $target)->isEmpty());
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
