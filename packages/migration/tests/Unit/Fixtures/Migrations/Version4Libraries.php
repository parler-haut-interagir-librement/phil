<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;

final class Version4Libraries extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Mise à jour des librairies.']);
        $this->addSql('CREATE TABLE libraries (id INTEGER)');
    }
}
