<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Integration;

use Override;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineSyndicationRepository::class)]
#[Group('mysql')]
final class MySqlSyndicationTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
