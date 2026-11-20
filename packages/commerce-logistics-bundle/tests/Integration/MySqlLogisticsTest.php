<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Integration;

use Override;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineLogisticsRepository::class)]
#[Group('mysql')]
final class MySqlLogisticsTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
