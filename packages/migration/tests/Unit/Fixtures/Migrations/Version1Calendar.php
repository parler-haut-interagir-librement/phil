<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;
use Phil\Migration\News;

final class Version1Calendar extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        $this->news(['fr' => new News('Le calendrier', ['Tous les événements.'], 'https://example.com/calendrier')]);
        $this->addSql('CREATE TABLE calendar (id INTEGER)');
    }
}
