<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit;

use Phil\EmailPreview\Finding;
use Phil\EmailPreview\Report;
use Phil\EmailPreview\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(Finding::class)]
final class ReportTest extends TestCase
{
    private Finding $error;

    private Finding $warning;

    private Report $report;

    protected function setUp(): void
    {
        $this->error = Finding::error('text-part', 'No <text> part.', 'https://a.test/?a=1&b="2"');
        $this->warning = Finding::warning('subject-length', 'Long subject.');
        $this->report = new Report(['text-part', 'subject-length', 'max-width'], [$this->error, $this->warning]);
    }

    public function testFindingsAreMadeBySeverity(): void
    {
        self::assertSame(Severity::Error, $this->error->severity);
        self::assertSame(Severity::Warning, $this->warning->severity);
        self::assertNull($this->warning->subject);
        self::assertSame(['rule' => 'subject-length', 'severity' => 'warning', 'message' => 'Long subject.', 'subject' => null], $this->warning->toArray());
    }

    public function testAReportPassesWithoutErrorAndStrictlyWithoutFinding(): void
    {
        self::assertFalse($this->report->passes());
        self::assertFalse($this->report->passes(strict: true));

        $warnings = new Report(['subject-length'], [$this->warning]);
        self::assertTrue($warnings->passes());
        self::assertFalse($warnings->passes(strict: true));
        self::assertTrue(new Report()->passes(strict: true));
    }

    public function testTheFindingsAreFilteredBySeverityAndRule(): void
    {
        self::assertSame([$this->error], $this->report->errors());
        self::assertSame([$this->warning], $this->report->warnings());
        self::assertSame([$this->warning], $this->report->ofRule('subject-length'));
        self::assertSame([], $this->report->ofRule('max-width'));
        // The lists are lists: their keys start at zero whatever the findings left out before.
        self::assertSame([0], array_keys(new Report([], [Finding::warning('a', 'x'), $this->error])->errors()));
        self::assertSame([0, 1], array_keys(new Report([], [$this->error, Finding::warning('a', 'x'), $this->warning])->warnings()));
        self::assertSame([0], array_keys($this->report->ofRule('subject-length')));
        self::assertCount(2, $this->report);
    }

    public function testTheReportIsWrittenAsText(): void
    {
        self::assertSame(
            "[error] text-part: No <text> part. (https://a.test/?a=1&b=\"2\")\n[warning] subject-length: Long subject.",
            $this->report->toText(),
        );
        self::assertSame('No finding: the email passes the 2 rules.', new Report(['a', 'b'])->toText());
    }

    public function testTheReportIsWrittenAsEscapedHtml(): void
    {
        self::assertSame(
            '<ul class="email-preview-report">'
            .'<li data-severity="error" data-rule="text-part"><strong>text-part</strong> No &lt;text&gt; part. <code>https://a.test/?a=1&amp;b=&quot;2&quot;</code></li>'
            .'<li data-severity="warning" data-rule="subject-length"><strong>subject-length</strong> Long subject.</li>'
            .'</ul>',
            $this->report->toHtml(),
        );
        self::assertSame('<p class="email-preview-report is-passed">No finding: the email passes the 1 rules.</p>', new Report(['a'])->toHtml());
        self::assertStringContainsString('<strong>&lt;b&gt;&apos;</strong>', new Report([], [Finding::error("<b>'", "\xFF")])->toHtml());
        self::assertStringContainsString("</strong> \u{FFFD}</li>", new Report([], [Finding::error('a', "\xFF")])->toHtml());
    }

    public function testTheReportIsGivenAsJson(): void
    {
        $expected = [
            'passed' => false,
            'counts' => ['error' => 1, 'warning' => 1],
            'rules' => ['text-part', 'subject-length', 'max-width'],
            'findings' => [
                ['rule' => 'text-part', 'severity' => 'error', 'message' => 'No <text> part.', 'subject' => 'https://a.test/?a=1&b="2"'],
                ['rule' => 'subject-length', 'severity' => 'warning', 'message' => 'Long subject.', 'subject' => null],
            ],
        ];

        self::assertSame($expected, $this->report->toArray());
        self::assertSame($expected, $this->report->jsonSerialize());
    }
}
