<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Phil\MentionsBundle\Infrastructure\Doctrine\MentionInsert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MentionInsert::class)]
final class MentionInsertTest extends TestCase
{
    private const string INSERT = 'INSERT INTO phil_mention (id, subject, member, author, created_at) VALUES (?, ?, ?, ?, ?)';

    public function testEachPlatformKeepsTheRowFound(): void
    {
        self::assertSame(self::INSERT.' ON CONFLICT (subject, member) DO NOTHING', MentionInsert::sql(new PostgreSQLPlatform()));
        self::assertSame(self::INSERT.' ON CONFLICT (subject, member) DO NOTHING', MentionInsert::sql(new SQLitePlatform()));
        self::assertSame(self::INSERT.' ON DUPLICATE KEY UPDATE id = id', MentionInsert::sql(new MySQLPlatform()));
        self::assertSame(self::INSERT.' ON DUPLICATE KEY UPDATE id = id', MentionInsert::sql(new MariaDBPlatform()));
        self::assertSame(self::INSERT, MentionInsert::sql(new SQLServerPlatform()));
    }
}
