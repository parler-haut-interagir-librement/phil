<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\Exception\InvalidReport;
use Phil\BrowserSecurityBundle\Application\ReportParser;
use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\StrictReports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportParser::class)]
#[CoversClass(InvalidReport::class)]
final class ReportParserTest extends TestCase
{
    public function testTheMediaTypes(): void
    {
        $parser = new ReportParser();

        self::assertSame('application/csp-report', ReportParser::mediaType(' Application/CSP-Report ; charset=utf-8'));
        self::assertTrue($parser->supports('application/csp-report'));
        self::assertTrue($parser->supports('application/reports+json; charset=utf-8'));
        self::assertTrue($parser->supports('application/json'));
        self::assertFalse($parser->supports('text/plain'));
        self::assertFalse($parser->supports(''));
    }

    public function testALegacyReport(): void
    {
        $body = json_encode(['csp-report' => [
            'document-uri' => 'https://user:secret@example.com/page?token=abc#top',
            'referrer' => 'https://other.example/',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'original-policy' => "script-src 'self'",
            'disposition' => 'report',
            'blocked-uri' => 'https://evil.example/x.js?id=1',
            'line-number' => 12,
            'column-number' => '4',
            'source-file' => 'https://example.com/app.js#L1',
            'status-code' => 200,
            'script-sample' => str_repeat('é', 50),
        ]], \JSON_THROW_ON_ERROR);

        StrictReports::assertSame(
            [new ViolationReport('https://example.com/page', 'https://evil.example/x.js', 'script-src-elem', Disposition::Report, 'https://example.com/app.js', 12, 4, 200, str_repeat('é', 40))],
            new ReportParser()->parse('application/csp-report', $body),
        );
    }

    public function testALegacyReportWithTheViolatedDirectiveOnly(): void
    {
        $body = '{"csp-report":{"document-uri":"https://example.com/","violated-directive":"img-src \'self\'","disposition":"maybe","line-number":-1,"column-number":"1e3","status-code":1000000001,"script-sample":""}}';

        StrictReports::assertSame([new ViolationReport('https://example.com/', '', 'img-src')], new ReportParser()->parse('application/json', $body));
    }

    public function testTheViolatedDirectiveIsTrimmedThenCutAtItsFirstSpace(): void
    {
        $parser = new ReportParser();
        $report = static fn (string $directive): string => '{"csp-report":{"document-uri":"https://example.com/","violated-directive":'.json_encode($directive, \JSON_THROW_ON_ERROR).'}}';

        self::assertSame('img-src', $parser->parse('application/csp-report', $report(" img-src 'self' data:"))[0]->effectiveDirective);
        self::assertSame([], $parser->parse('application/csp-report', $report("img-src\n 'self'")));
    }

    public function testAnItemThatIsNotAReportIsSkipped(): void
    {
        $body = json_encode([3, ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'img-src']]], \JSON_THROW_ON_ERROR);

        self::assertCount(1, new ReportParser()->parse('application/reports+json', $body));
    }

    public function testAReportingApiItemCannotChooseTheLegacyKeys(): void
    {
        $body = json_encode([['type' => 'csp-violation', 'legacy' => true, 'body' => ['document-uri' => 'https://example.com/', 'effective-directive' => 'img-src']]], \JSON_THROW_ON_ERROR);

        self::assertSame([], new ReportParser()->parse('application/reports+json', $body));
    }

    public function testReportsOfTheReportingApi(): void
    {
        $body = json_encode([
            ['type' => 'deprecation', 'body' => ['documentURL' => 'https://example.com/']],
            ['type' => 'csp-violation', 'age' => 3, 'url' => 'https://example.com/', 'user_agent' => 'Firefox', 'body' => [
                'documentURL' => 'https://example.com/a',
                'blockedURL' => 'inline',
                'effectiveDirective' => 'style-src-attr',
                'violatedDirective' => 'style-src',
                'disposition' => 'enforce',
                'sourceFile' => 'https://example.com/a',
                'lineNumber' => 1000000000,
                'columnNumber' => 0,
                'statusCode' => '200',
                'sample' => "color:\x01red",
            ]],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/b', 'violatedDirective' => 'img-src']],
            ['type' => 'csp-violation', 'body' => 'nothing'],
            'nothing',
            ['body' => ['documentURL' => 'https://example.com/c', 'effectiveDirective' => 'img-src']],
        ], \JSON_THROW_ON_ERROR);

        StrictReports::assertSame([
            new ViolationReport('https://example.com/a', 'inline', 'style-src-attr', Disposition::Enforce, 'https://example.com/a', 1000000000, 0, 200, 'color:red'),
            new ViolationReport('https://example.com/b', '', 'img-src'),
        ], new ReportParser()->parse('application/reports+json', $body));
    }

    public function testAReportWithoutAPageOrADirectiveIsDropped(): void
    {
        $body = json_encode([
            ['type' => 'csp-violation', 'body' => ['effectiveDirective' => 'img-src']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => '?only=query', 'effectiveDirective' => 'img-src']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'Img-Src']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'img-src;x']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => '-img']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'a'.str_repeat('b', 64)]],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 7]],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 7, 'effectiveDirective' => 'img-src']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'a'.str_repeat('b', 63), 'sample' => 7, 'blockedURL' => 7]],
        ], \JSON_THROW_ON_ERROR);

        StrictReports::assertSame([new ViolationReport('https://example.com/', '', 'a'.str_repeat('b', 63))], new ReportParser()->parse('application/reports+json', $body));
    }

    public function testTheBoundsOfTheFields(): void
    {
        $long = 'https://example.com/a'.str_repeat('é', 2000);
        $body = json_encode([['type' => 'csp-violation', 'body' => ['documentURL' => $long, 'effectiveDirective' => 'img-src', 'blockedURL' => "https://a.example/\x00x"]]], \JSON_THROW_ON_ERROR);
        $reports = new ReportParser()->parse('application/json', $body);

        self::assertCount(1, $reports);
        self::assertSame(mb_strcut($long, 0, ReportParser::MAX_URI_LENGTH, 'UTF-8'), $reports[0]->documentUri);
        self::assertTrue(mb_check_encoding($reports[0]->documentUri, 'UTF-8'));
        self::assertLessThanOrEqual(ReportParser::MAX_URI_LENGTH, \strlen($reports[0]->documentUri));
        self::assertSame('https://a.example/x', $reports[0]->blockedUri);
    }

    public function testInvalidUtf8IsScrubbed(): void
    {
        $body = '[{"type":"csp-violation","body":{"documentURL":"https://example.com/é","effectiveDirective":"img-src","sample":"a'."\xff".'b"}}]';

        try {
            new ReportParser()->parse('application/reports+json', $body);
            self::fail('A body that is not UTF-8 was read.');
        } catch (InvalidReport $invalidReport) {
            self::assertSame('The report body is not JSON within eight levels.', $invalidReport->getMessage());
            self::assertNotNull($invalidReport->getPrevious());
        }
    }

    public function testABodyGivesABoundedNumberOfReports(): void
    {
        $report = ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/', 'effectiveDirective' => 'img-src']];
        $body = json_encode(array_fill(0, 5, $report), \JSON_THROW_ON_ERROR);

        self::assertCount(3, new ReportParser(3)->parse('application/reports+json', $body));
        self::assertCount(5, new ReportParser(5)->parse('application/reports+json', $body));
        self::assertCount(ReportParser::DEFAULT_MAX_REPORTS, new ReportParser()->parse('application/reports+json', json_encode(array_fill(0, 30, $report), \JSON_THROW_ON_ERROR)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'not JSON' => ['application/csp-report', '{', 'The report body is not JSON within eight levels.'];
        yield 'too deep' => ['application/json', str_repeat('[', 9).str_repeat(']', 9), 'The report body is not JSON within eight levels.'];
        yield 'a scalar' => ['application/json', '"report"', 'The report body is not in the shape of application/json.'];
        yield 'a map for the reporting api' => ['application/reports+json', '{"type":"csp-violation"}', 'The report body is not in the shape of application/reports+json.'];
        yield 'a list for a legacy report' => ['application/csp-report', '[]', 'The report body is not in the shape of application/csp-report.'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedBody(string $type, string $body, string $message): void
    {
        $this->expectException(InvalidReport::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ReportParser()->parse($type, $body);
    }

    public function testEightLevelsAreRead(): void
    {
        self::assertSame([], new ReportParser()->parse('application/json', str_repeat('[', 7).str_repeat(']', 7)));
        self::assertSame([], new ReportParser()->parse('application/csp-report', '{"csp-report":"nothing"}'));
        self::assertSame([], new ReportParser()->parse('application/json', '{"other":{}}'));
    }

    public function testABigNumberIsDropped(): void
    {
        $body = '{"csp-report":{"document-uri":"https://example.com/","effective-directive":"img-src","line-number":123456789012345678901234567890}}';

        self::assertNull(new ReportParser()->parse('application/csp-report', $body)[0]->line);
    }

    public function testTheReportsOfTheOtherPolicies(): void
    {
        $body = (string) json_encode([
            ['type' => 'coop', 'url' => 'https://example.com/a', 'body' => ['disposition' => 'reporting']],
            ['type' => 'coep', 'url' => 'https://example.com/b', 'body' => ['blockedURL' => 'https://cdn.example.net/x', 'disposition' => 'enforce']],
            ['type' => 'integrity-violation', 'url' => 'https://example.com/ignored', 'body' => ['documentURL' => 'https://example.com/c', 'blockedURL' => 'https://cdn.example.net/y', 'reportOnly' => false]],
            ['type' => 'integrity-violation', 'body' => ['documentURL' => 'https://example.com/d', 'reportOnly' => true]],
            ['type' => 'coop', 'url' => 'https://example.com/e', 'body' => 'nothing'],
            ['type' => 3, 'body' => []],
            'not a report',
        ]);

        $reports = new ReportParser()->parse(ReportParser::REPORTS_JSON, $body);

        self::assertSame(
            [
                ['https://example.com/a', '', 'cross-origin-opener-policy', Disposition::Report],
                ['https://example.com/b', 'https://cdn.example.net/x', 'cross-origin-embedder-policy', Disposition::Enforce],
                ['https://example.com/c', 'https://cdn.example.net/y', 'integrity-policy', Disposition::Enforce],
                ['https://example.com/d', '', 'integrity-policy', Disposition::Report],
            ],
            array_map(static fn (ViolationReport $report): array => [$report->documentUri, $report->blockedUri, $report->effectiveDirective, $report->disposition], $reports),
        );
    }
}
