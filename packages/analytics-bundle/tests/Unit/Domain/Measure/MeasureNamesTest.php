<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Measure;

use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasureNames::class)]
final class MeasureNamesTest extends TestCase
{
    public function testEveryMetricTheBundleWritesUnderItsOwnNameIsAMetric(): void
    {
        self::assertCount(12, MeasureNames::METRICS);

        foreach (MeasureNames::METRICS as $metric) {
            self::assertTrue(MeasureNames::isMetric($metric), $metric);
        }

        foreach (MeasureNames::PRIVATE_METRICS as $metric) {
            self::assertContains($metric, MeasureNames::METRICS);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namedMetrics(): iterable
    {
        yield 'a goal' => ['goals.signup'];
        yield 'a goal with a dot' => ['goals.signup.newsletter'];
        yield 'a server event' => ['server_events.ticket-sold'];
        yield 'the longest name' => ['goals.'.str_repeat('a', 58)];
    }

    #[DataProvider('namedMetrics')]
    public function testAMetricOfAGoalOrServerEventIsAMetric(string $metric): void
    {
        self::assertTrue(MeasureNames::isMetric($metric));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notMetrics(): iterable
    {
        yield 'the private links in capitals' => ['OUTBOUND_LINK'];
        yield 'the private links with one capital' => ['Outbound_link'];
        yield 'the searches in capitals' => ['SEARCH'];
        yield 'page views with a capital' => ['Pageviews'];
        yield 'surrounding space' => [' pageviews'];
        yield 'a trailing space' => ['outbound_link '];
        yield 'an unknown metric' => ['sessions'];
        yield 'empty' => [''];
        yield 'a named metric without a name' => ['goals.'];
        yield 'a named metric whose name starts with a digit' => ['goals.1st'];
        yield 'a named metric whose name has a capital' => ['goals.Signup'];
        yield 'a family in capitals' => ['GOALS.signup'];
        yield 'a metric that is not a family' => ['pageviews.home'];
        yield 'the private links as a family' => ['outbound_link.home'];
        yield 'too long' => ['goals.'.str_repeat('a', 59)];
        yield 'a dot first' => ['.goals'];
    }

    #[DataProvider('notMetrics')]
    public function testAnythingElseIsNoMetric(string $metric): void
    {
        self::assertFalse(MeasureNames::isMetric($metric));
    }
}
