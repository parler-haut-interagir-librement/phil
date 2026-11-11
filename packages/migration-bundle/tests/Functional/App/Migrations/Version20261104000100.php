<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Functional\App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;

final class Version20261104000100 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Mise à jour des librairies.']);
        $this->addSql('CREATE TABLE libraries (id INTEGER)');
    }
}
