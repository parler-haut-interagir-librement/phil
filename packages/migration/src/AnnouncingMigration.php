<?php

declare(strict_types=1);

namespace Phil\Migration;

use Doctrine\Migrations\AbstractMigration;

/**
 * A Doctrine migration that announces what it brings with `note()` and `news()`.
 */
abstract class AnnouncingMigration extends AbstractMigration implements Announcing
{
    use Announces;
}
