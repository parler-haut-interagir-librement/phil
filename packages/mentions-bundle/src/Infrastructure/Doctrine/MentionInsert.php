<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Phil\MentionsBundle\Domain\Model\RecordedMention;

/**
 * The statement that inserts a row of `phil_mention` unless its subject already mentions its member, keeping the row
 * found: `ON CONFLICT DO NOTHING` on PostgreSQL and SQLite, `ON DUPLICATE KEY UPDATE` on MySQL and MariaDB. Another
 * platform gets a plain insert, which its unique constraint refuses for a member already mentioned. The values are
 * bound in the order of `id`, `subject`, `member`, `author` and `created_at`.
 */
final class MentionInsert
{
    public static function sql(AbstractPlatform $platform): string
    {
        $insert = 'INSERT INTO '.RecordedMention::TABLE.' (id, subject, member, author, created_at) VALUES (?, ?, ?, ?, ?)';

        if ($platform instanceof PostgreSQLPlatform || $platform instanceof SQLitePlatform) {
            return $insert.' ON CONFLICT (subject, member) DO NOTHING';
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return $insert.' ON DUPLICATE KEY UPDATE id = id';
        }

        return $insert;
    }
}
