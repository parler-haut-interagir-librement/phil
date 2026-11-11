<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Restore;

use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Exception\RestoreRefused;
use Phil\Backup\Restore\RestoreGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Production, then the confirmation, then the name typed again.
 */
#[CoversClass(RestoreGuard::class)]
#[CoversClass(RestoreRefused::class)]
final class RestoreGuardTest extends TestCase
{
    public function testEachCheckRefusesInItsTurn(): void
    {
        $shop = new DatabaseIdentity('mysql', 'db.test', 'shop');
        $file = new DatabaseIdentity('sqlite', null, '/data/app.sqlite');

        foreach ([
            'A restore is refused in the "prod" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, shop, for this run alone.' => [new RestoreGuard('prod'), $shop, null, true, 'shop'],
            'A restore is refused in the "prod" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, shop, for this run alone. ' => [new RestoreGuard('prod'), $shop, '1', true, 'shop'],
            'A restore is refused in the "live" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, app.sqlite, for this run alone.' => [new RestoreGuard('live', ['staging', 'live']), $file, 'shop', true, 'app.sqlite'],
            'Nothing was restored: add --confirm to replace the content of the mysql database shop on db.test with b.sql.' => [new RestoreGuard('dev'), $shop, null, false, 'shop'],
            'Nothing was restored: type the name of the database again, shop, with --database.' => [new RestoreGuard('dev'), $shop, null, true, null],
            'Nothing was restored: "Shop" is not the name of the database, shop.' => [new RestoreGuard('dev'), $shop, null, true, 'Shop'],
            'Nothing was restored: "/data/app.sqlite" is not the name of the database, app.sqlite.' => [new RestoreGuard('prod', []), $file, null, true, '/data/app.sqlite'],
        ] as $message => [$guard, $database, $allowance, $confirmed, $typed]) {
            try {
                $guard->check($database, $allowance, $confirmed, $typed, 'b.sql');
                self::fail($message);
            } catch (RestoreRefused $restoreRefused) {
                self::assertSame(rtrim($message), $restoreRefused->getMessage());
            }
        }
    }

    public function testARestoreThatPassesEveryCheckGoesOn(): void
    {
        $shop = new DatabaseIdentity('postgresql', null, 'shop');

        new RestoreGuard('dev')->check($shop, null, true, 'shop', 'b.dump');
        new RestoreGuard('prod')->check($shop, 'shop', true, 'shop', 'b.dump');
        new RestoreGuard('prod')->checkEnvironment($shop, 'shop');
        new RestoreGuard('test')->checkEnvironment($shop, null);
        $this->addToAssertionCount(4);
        self::assertSame('PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE', RestoreGuard::VARIABLE);
    }
}
