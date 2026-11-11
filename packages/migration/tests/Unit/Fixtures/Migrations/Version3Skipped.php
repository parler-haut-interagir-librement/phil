<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;

final class Version3Skipped extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Jamais annoncée.']);
        $this->skipIf(true, 'Nothing to do.');
    }
}
