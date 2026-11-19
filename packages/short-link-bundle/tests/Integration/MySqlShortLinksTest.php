<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Integration;

use Override;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Infrastructure\Doctrine\DoctrineShortLinkRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineShortLinkRepository::class)]
#[CoversClass(ShortLink::class)]
#[Group('mysql')]
final class MySqlShortLinksTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
