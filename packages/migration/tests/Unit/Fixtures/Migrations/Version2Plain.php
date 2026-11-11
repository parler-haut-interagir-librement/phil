<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version2Plain extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plain (id INTEGER)');
    }
}
