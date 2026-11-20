<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;

/**
 * The tables of phil/organizations-bundle the database lacks: those of the units and their members on a database of
 * 0.1, every table on an empty one.
 */
final class Version20261120000000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'phil/organizations-bundle 0.2: the tables of the units and their members.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $tables = OrganizationTables::for($this->connection->getDatabasePlatform());

        foreach ($tables->upgrade($schema, $this->connection->createSchemaManager()->createComparator()) as $statement) {
            $this->addSql($statement);
        }
    }

    #[Override]
    public function down(Schema $schema): void
    {
        foreach (OrganizationTables::for($this->connection->getDatabasePlatform())->downgrade() as $statement) {
            $this->addSql($statement);
        }
    }
}
