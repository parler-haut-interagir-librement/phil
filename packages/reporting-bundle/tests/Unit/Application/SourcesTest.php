<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Tests\Unit\Fixture\OrdersSource;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use Phil\ReportingBundle\Tests\Unit\Fixture\VisitsSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(Sources::class)]
#[CoversClass(NotFound::class)]
final class SourcesTest extends TestCase
{
    public function testTheSourcesByKey(): void
    {
        $orders = new OrdersSource();
        $sources = Reporting::sources($orders);

        self::assertSame($orders, $sources->metric('orders'));
        self::assertInstanceOf(VisitsSource::class, $sources->series('visits'));
        self::assertSame(['orders'], $sources->metricKeys());
        self::assertSame(['visits'], $sources->seriesKeys());

        $many = new Sources(
            // A numeric key, which PHP turns into an integer: the keys are given back as strings.
            new ServiceLocator(['zeta' => static fn (): OrdersSource => $orders, 'alpha' => static fn (): OrdersSource => $orders, '7' => static fn (): OrdersSource => $orders]), // @phpstan-ignore argument.type
            new ServiceLocator(['b' => static fn (): VisitsSource => new VisitsSource(), 'a' => static fn (): VisitsSource => new VisitsSource()]),
        );
        self::assertSame(['7', 'alpha', 'zeta'], $many->metricKeys());
        self::assertSame(['a', 'b'], $many->seriesKeys());
    }

    public function testAnUnknownSource(): void
    {
        foreach (['metric source' => static fn (): object => Reporting::sources()->metric('visits'), 'series source' => static fn (): object => Reporting::sources()->series('orders')] as $kind => $find) {
            try {
                $find();
                self::fail($kind);
            } catch (NotFound $notFound) {
                self::assertStringStartsWith('There is no '.$kind.' "', $notFound->getMessage());
            }
        }
    }
}
