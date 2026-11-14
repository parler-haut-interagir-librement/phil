<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Functional\App\ServiceMigrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;

/**
 * A migration DoctrineMigrationsBundle gets from the container, through `enable_service_migrations`, rather than
 * from a directory.
 */
final class Version20261104000200 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Migrations en service.']);
        $this->addSql('CREATE TABLE services (id INTEGER)');
    }
}
