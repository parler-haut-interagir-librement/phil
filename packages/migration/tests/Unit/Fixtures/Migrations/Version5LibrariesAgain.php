<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;
use Phil\Migration\News;

final class Version5LibrariesAgain extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Passage à PHP 8.5.']);
        $this->note(['fr' => '  * Mise à jour des librairies. ', 'en' => '* Mise à jour des librairies.']);
        $this->news(['fr' => new News('Le calendrier', [' Tous les événements.'], 'https://example.com/calendrier')]);
        $this->addSql('CREATE TABLE php (id INTEGER)');
    }
}
