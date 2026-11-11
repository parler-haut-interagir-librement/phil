<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Failing;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;

final class Version1Kept extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Gardée.']);
        $this->addSql('CREATE TABLE kept (id INTEGER)');
    }
}
