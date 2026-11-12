<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Reading;

use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReaderScope::class)]
final class ReaderScopeTest extends TestCase
{
    public function testEverySiteCoversAnySite(): void
    {
        $scope = ReaderScope::everySite();

        self::assertTrue($scope->coversEverySite());
        self::assertTrue($scope->mayRead(SiteId::fromString('anything')));
    }

    public function testAListOfSitesCoversThoseSitesOnly(): void
    {
        $scope = ReaderScope::ofSites(SiteId::fromString('spring-con'), SiteId::fromString('autumn'), SiteId::fromString('autumn'));

        self::assertFalse($scope->coversEverySite());
        self::assertTrue($scope->mayRead(SiteId::fromString('spring-con')));
        self::assertTrue($scope->mayRead(SiteId::fromString('autumn')));
        self::assertFalse($scope->mayRead(SiteId::fromString('winter')));
    }

    public function testAScopeOfNoSiteIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A reader scope names 1 to 1000 sites; 0 were given.');

        ReaderScope::ofSites();
    }

    public function testTheLargestScopeIsAcceptedAndOneMoreIsRefused(): void
    {
        $sites = array_map(static fn (int $i): SiteId => SiteId::fromString('site-'.$i), range(1, ReaderScope::MAX_SITES));

        self::assertTrue(ReaderScope::ofSites(...$sites)->mayRead(SiteId::fromString('site-1000')));

        $sites[] = SiteId::fromString('site-1001');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('1001 were given');

        ReaderScope::ofSites(...$sites);
    }
}
