<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use PHPUnit\Framework\Assert;

/**
 * A text kept in `Fixture/snapshots/`. `PHIL_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit` writes it again after a change that
 * is meant.
 */
final class Snapshot
{
    public static function assertSame(string $name, string $actual): void
    {
        $file = __DIR__.'/snapshots/'.$name.'.txt';

        if (false !== getenv('PHIL_UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o777, true);
            }

            file_put_contents($file, $actual);
        }

        Assert::assertFileExists($file);
        Assert::assertSame(file_get_contents($file), $actual);
    }
}
