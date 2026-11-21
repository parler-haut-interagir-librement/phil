<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Integration;

use Override;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(GiftCards::class)]
#[CoversClass(StoreCredits::class)]
#[CoversClass(DoctrineStoredValueRepository::class)]
#[Group('mysql')]
final class MySqlIssuanceTest extends ServerTestCase
{
    #[Override]
    protected function url(): string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        return $url;
    }
}
