<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectionSite::class)]
final class CollectionSiteTest extends TestCase
{
    public function testASiteDeclaresItsOriginsAndNoOther(): void
    {
        $site = CollectionSite::of(
            SiteId::fromString('s'),
            [Origin::fromString('https://example.com'), Origin::fromString('https://www.example.com')],
            DeclaredRoutes::none(),
            SiteStatus::Active,
        );

        self::assertTrue($site->declares(Origin::fromString('https://WWW.example.com')));
        self::assertFalse($site->declares(Origin::fromString('http://example.com')));
        self::assertFalse($site->declares(Origin::fromString('https://shop.example.com')));
    }

    public function testASiteKeepsWhatItMeasures(): void
    {
        $plan = MeasurePlan::of(ClickTracking::Off);
        $site = CollectionSite::of(SiteId::fromString('s'), [Origin::fromString('https://example.com')], DeclaredRoutes::none(), SiteStatus::Active, $plan);

        self::assertSame($plan, $site->plan);
        self::assertSame(ClickTracking::Aggregate, CollectionSite::of(SiteId::fromString('s'), [Origin::fromString('https://example.com')], DeclaredRoutes::none(), SiteStatus::Active)->plan->clicks);
    }

    public function testASiteWithoutOriginIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        CollectionSite::of(SiteId::fromString('s'), [], DeclaredRoutes::none(), SiteStatus::Active);
    }

    public function testAnOriginDeclaredTwiceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        CollectionSite::of(
            SiteId::fromString('s'),
            [Origin::fromString('https://example.com'), Origin::fromString('https://example.com:443')],
            DeclaredRoutes::none(),
            SiteStatus::Active,
        );
    }
}
