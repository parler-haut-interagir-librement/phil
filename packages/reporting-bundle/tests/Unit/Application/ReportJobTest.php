<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportJob::class)]
#[CoversClass(ReportRequest::class)]
#[CoversClass(InvalidReportJob::class)]
final class ReportJobTest extends TestCase
{
    public function testARequest(): void
    {
        $request = Reporting::request(['status' => 'paid', 'rows' => 2, 'ratio' => 0.5, 'open' => false]);

        self::assertSame('paid', $request->parameter('status'));
        self::assertNull($request->parameter('nope'));
        self::assertSame(7, $request->parameter('nope', 7));
        self::assertFalse($request->parameter('open', true));
        self::assertSame([
            'start' => '2026-10-01T00:00:00-04:00',
            'end' => '2026-11-01T00:00:00-04:00',
            'timezone' => 'America/Toronto',
            'parameters' => ['status' => 'paid', 'rows' => 2, 'ratio' => 0.5, 'open' => false],
        ], $request->toArray());
        self::assertEquals($request, ReportRequest::fromArray($request->toArray()));
        self::assertSame([], Reporting::request()->parameters);

        $winter = ReportRequest::fromArray(['start' => '2026-12-01T05:00:00+00:00', 'end' => '2027-01-01T05:00:00+00:00', 'timezone' => 'America/Toronto', 'parameters' => []]);
        self::assertSame('2026-12-01T00:00:00-05:00 America/Toronto', $winter?->period->start->format(\DATE_ATOM.' e'));
        self::assertSame('2027-01-01T00:00:00-05:00 America/Toronto', $winter->period->end->format(\DATE_ATOM.' e'));
    }

    public function testTheLimitsOfARequest(): void
    {
        self::assertCount(32, Reporting::request(array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 32)), 1))->parameters);
        self::assertCount(1, Reporting::request([str_repeat('a', 64) => str_repeat('é', 200)])->parameters);
        self::assertCount(1, Reporting::request(['A-z_9.x' => true])->parameters);

        foreach ([
            'more than 32 parameters' => array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 33)), 1),
            'the parameter "'.str_repeat('a', 65).'" is not named by 1 to 64 letters, digits, dots, dashes or underscores' => [str_repeat('a', 65) => 1],
            'the parameter "a b" is not named by 1 to 64 letters, digits, dots, dashes or underscores' => ['a b' => 1],
            'the parameter "x" is longer than 200 characters' => ['x' => str_repeat('é', 201)],
        ] as $problem => $parameters) {
            try {
                Reporting::request($parameters);
                self::fail($problem);
            } catch (InvalidReportJob $invalidReportJob) {
                self::assertSame('The report job is invalid: '.$problem.'.', $invalidReportJob->getMessage());
            }
        }
    }

    #[DataProvider('badRequests')]
    public function testARequestItDidNotWrite(mixed $data): void
    {
        self::assertNull(ReportRequest::fromArray($data));
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function badRequests(): iterable
    {
        $good = Reporting::request()->toArray();

        yield ['nope'];
        yield [[]];

        foreach (['start', 'end', 'timezone', 'parameters'] as $key) {
            yield [[$key => 1] + $good];
            $missing = $good;
            unset($missing[$key]);

            yield [$missing];
        }

        yield [['parameters' => [1 => 'x']] + $good];
        yield [['parameters' => ['x' => ['y']]] + $good];
        yield [['parameters' => ['x' => null]] + $good];
        yield [['parameters' => ['a b' => 1]] + $good];
        yield [['timezone' => 'Mars/Olympus'] + $good];
        yield [['start' => 'soon'] + $good];
        yield [['end' => $good['start']] + $good];
    }

    public function testAJob(): void
    {
        $job = new ReportJob('sales', 'csv', Reporting::request(), 'mail', ['ada@example.com']);

        self::assertSame(['report' => 'sales', 'format' => 'csv', 'request' => Reporting::request()->toArray(), 'delivery' => 'mail', 'recipients' => ['ada@example.com']], $job->toArray());
        self::assertEquals($job, ReportJob::fromArray($job->toArray()));
        $bare = new ReportJob('sales', 'csv', Reporting::request());
        self::assertSame([null, []], [$bare->delivery, $bare->recipients]);
        self::assertEquals($bare, ReportJob::fromArray($bare->toArray()));
        self::assertCount(100, new ReportJob('sales', 'csv', Reporting::request(), 'mail', array_fill(0, 100, str_repeat('a', 254)))->recipients);
    }

    public function testTheLimitsOfAJob(): void
    {
        foreach ([
            'the report "a/b" is not named by 1 to 64 letters, digits, dots, dashes or underscores' => static fn (): ReportJob => new ReportJob('a/b', 'csv', Reporting::request()),
            'the format "" is not named by 1 to 64 letters, digits, dots, dashes or underscores' => static fn (): ReportJob => new ReportJob('sales', '', Reporting::request()),
            'the delivery "a b" is not named by 1 to 64 letters, digits, dots, dashes or underscores' => static fn (): ReportJob => new ReportJob('sales', 'csv', Reporting::request(), 'a b'),
            'more than 100 recipients' => static fn (): ReportJob => new ReportJob('sales', 'csv', Reporting::request(), 'mail', array_fill(0, 101, 'a')),
            'a recipient is from 1 to 254 bytes' => static fn (): ReportJob => new ReportJob('sales', 'csv', Reporting::request(), 'mail', ['']),
            'a recipient is from 1 to 254 bytes ' => static fn (): ReportJob => new ReportJob('sales', 'csv', Reporting::request(), 'mail', [str_repeat('a', 255)]),
        ] as $problem => $build) {
            try {
                $build();
                self::fail($problem);
            } catch (InvalidReportJob $invalidReportJob) {
                self::assertSame('The report job is invalid: '.trim($problem).'.', $invalidReportJob->getMessage());
            }
        }
    }

    #[DataProvider('badJobs')]
    public function testAJobItDidNotWrite(mixed $data): void
    {
        self::assertNull(ReportJob::fromArray($data));
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function badJobs(): iterable
    {
        $good = new ReportJob('sales', 'csv', Reporting::request(), 'mail', ['ada@example.com'])->toArray();

        yield [null];

        foreach (['report', 'format', 'request', 'recipients'] as $key) {
            $missing = $good;
            unset($missing[$key]);

            yield [$missing];
        }

        yield [['report' => 1] + $good];
        yield [['format' => 1] + $good];
        yield [['request' => []] + $good];
        yield [['delivery' => 1] + $good];
        yield [['recipients' => 'ada@example.com'] + $good];
        yield [['recipients' => [1]] + $good];
        yield [['recipients' => ['ada@example.com', null]] + $good];
        yield [['report' => 'a b'] + $good];
    }
}
