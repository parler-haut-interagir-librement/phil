<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Failing;

use Doctrine\DBAL\Schema\Schema;
use Phil\Migration\AnnouncingMigration;
use RuntimeException;

final class Version2Broken extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Brisée.']);

        throw new RuntimeException('Broken.');
    }
}
