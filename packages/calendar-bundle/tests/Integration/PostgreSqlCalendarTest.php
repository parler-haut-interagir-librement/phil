<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Integration;

use Override;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineCalendarChoiceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrinePersonalFeedTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineCalendarChoiceRepository::class)]
#[CoversClass(DoctrinePersonalFeedTokenRepository::class)]
#[Group('postgresql')]
final class PostgreSqlCalendarTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
