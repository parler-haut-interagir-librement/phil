<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Integration;

use Override;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\PositionListener;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\Versions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * MySQL, as `phil-dev services up` starts it.
 *
 *     eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/doctrine-behaviours/tests/Integration
 */
#[CoversClass(DeactivatedFilter::class)]
#[CoversClass(SlugListener::class)]
#[CoversClass(Versions::class)]
#[CoversClass(OwnerEraser::class)]
#[CoversClass(Positions::class)]
#[CoversClass(PositionListener::class)]
#[CoversClass(Translations::class)]
#[CoversClass(TranslationListener::class)]
#[Group('mysql')]
final class MySqlBehavioursTest extends RealDatabaseTestCase
{
    #[Override]
    protected static function urlVariable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }

    #[Override]
    protected static function lockTimeout(): string
    {
        return 'SET SESSION innodb_lock_wait_timeout = 1';
    }
}
