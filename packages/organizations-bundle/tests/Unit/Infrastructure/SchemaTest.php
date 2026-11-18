<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use Phil\OrganizationsBundle\Infrastructure\Doctrine\OrganizationSchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationTables::class)]
#[CoversClass(OrganizationSchemaListener::class)]
final class SchemaTest extends TestCase
{
    public function testKeyColumnsAreBinaryOnMySql(): void
    {
        $sql = implode("\n", new MySQLPlatform()->getCreateTablesSQL(OrganizationTables::for(new MySQLPlatform())->tables()));

        self::assertStringContainsString('slug VARCHAR(255) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('member_id VARCHAR(128) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('token_hash VARCHAR(81) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('email_domain VARCHAR(253) CHARACTER SET ascii DEFAULT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('role VARCHAR(64) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('roles VARCHAR(1040) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $sql);
        self::assertStringContainsString('name VARCHAR(200) NOT NULL,', $sql);
        self::assertStringContainsString('created_at BIGINT NOT NULL', $sql);
        self::assertStringContainsString('archived_at BIGINT DEFAULT NULL', $sql);
        self::assertStringContainsString('uses INT DEFAULT 0 NOT NULL', $sql);
        self::assertStringContainsString('UNIQUE INDEX phil_organization_slug (slug)', $sql);
        self::assertStringContainsString('UNIQUE INDEX phil_organization_team_slug (organization_id, slug)', $sql);
        self::assertStringContainsString('UNIQUE INDEX phil_organization_invitation_token_hash (token_hash)', $sql);
        self::assertStringContainsString('PRIMARY KEY (organization_id, role, member_id)', $sql);
        self::assertStringContainsString('method VARCHAR(16) CHARACTER SET ascii NOT NULL', $sql);

        foreach ([
            'INDEX phil_organization_email_domain (email_domain)',
            'INDEX phil_organization_member_member_id (member_id, organization_id)',
            'INDEX phil_organization_member_role_member_id (member_id)',
            'INDEX phil_organization_team_member_member_id (member_id, organization_id)',
            'INDEX phil_organization_invitation_organization_id (organization_id, created_at)',
            'INDEX phil_organization_invitation_created_by (created_by)',
            'INDEX phil_organization_invitation_expires_at (expires_at)',
            'INDEX phil_organization_invitation_revoked_at (revoked_at)',
        ] as $index) {
            self::assertStringContainsString(', '.$index, $sql);
        }
    }

    public function testKeyColumnsTakeTheCCollationOnPostgreSql(): void
    {
        $sql = implode("\n", new PostgreSQLPlatform()->getCreateTablesSQL(OrganizationTables::for(new PostgreSQLPlatform())->tables()));

        self::assertStringContainsString('slug VARCHAR(255) NOT NULL COLLATE "C"', $sql);
        self::assertStringContainsString('id VARCHAR(128) NOT NULL COLLATE "C"', $sql);
        self::assertStringNotContainsString('name VARCHAR(200) NOT NULL COLLATE', $sql);
    }

    public function testSqliteComparesBytesByDefault(): void
    {
        $sql = implode("\n", new SQLitePlatform()->getCreateTablesSQL(OrganizationTables::for(new SQLitePlatform())->tables()));

        self::assertStringNotContainsString('COLLATE', $sql);
        self::assertSame(['phil_organization', 'phil_organization_member', 'phil_organization_member_role', 'phil_organization_team', 'phil_organization_team_member', 'phil_organization_invitation'], array_map(static fn (Table $t): string => $t->getObjectName()->toString(), OrganizationTables::for(new SQLitePlatform())->tables()));
    }

    public function testTheListenerAddsTheTablesToTheSchemaOfItsConnectionOnly(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([[$connection, true], [$other, false]] as [$of, $added]) {
            $manager = self::createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($of);
            $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());

            new OrganizationSchemaListener($connection)->postGenerateSchema($event);

            self::assertSame($added, $event->getSchema()->hasTable(OrganizationTables::INVITATION));
        }
    }
}
