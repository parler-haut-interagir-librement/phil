<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidReport;
use Phil\AccessibilityStatementBundle\Infrastructure\Audit\ReportDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The reports of a directory, written in a temporary one.
 */
#[CoversClass(ReportDirectory::class)]
final class ReportDirectoryTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-accessibility-statement-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $paths = glob($this->directory.'/{,.}*', \GLOB_BRACE);

        foreach (false === $paths ? [] : $paths as $path) {
            if (is_dir($path) && !\in_array(basename($path), ['.', '..'], true)) {
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->directory);
    }

    public function testEachReportIsAPageAndTheNewestDatesTheAudit(): void
    {
        $checker = new AccessibilityChecker();
        $this->write('home.json', (string) json_encode($checker->checkHtml('<img src="/a.jpg">')), 1_793_000_000);
        $this->write('about.json', (string) json_encode($checker->checkHtml('<p>Fine.</p>')), 1_793_100_000);
        $this->write('.hidden.json', '{', 1_793_200_000);
        $this->write('notes.txt', 'not a report', 1_793_300_000);
        $this->write('home.json.bak', '{', 1_793_400_000);
        mkdir($this->directory.'/old.json');

        $audit = new ReportDirectory($this->directory, method: EvaluationMethod::ThirdParty, reportUrl: '/audit')->latest();

        self::assertNotNull($audit);
        self::assertEquals(new DateTimeImmutable('@1793100000'), $audit->evaluatedAt);
        self::assertSame(['about', 'home'], array_map(static fn (AuditedPage $page): string => $page->page, $audit->pages));
        self::assertSame([], $audit->pages[0]->report->findings);
        self::assertSame('image-alt', $audit->pages[1]->report->findings[0]->rule);
        self::assertSame(EvaluationMethod::ThirdParty, $audit->method);
        self::assertSame('/audit', $audit->reportUrl);
        self::assertSame(EvaluationMethod::Automated, new ReportDirectory($this->directory)->latest()?->method);
    }

    public function testNoReportIsNoAudit(): void
    {
        self::assertNull(new ReportDirectory($this->directory)->latest());
        self::assertNull(new ReportDirectory($this->directory.'/missing')->latest());

        $this->write('readme.md', '#', 1_793_000_000);
        self::assertNull(new ReportDirectory($this->directory)->latest());
    }

    public function testADirectoryOfTooManyReportsIsRefused(): void
    {
        $this->write('a.json', '{}', 1_793_000_000);
        $this->write('b.json', '{}', 1_793_000_000);

        self::assertCount(2, new ReportDirectory($this->directory, maxFiles: 2)->latest()->pages ?? []);

        $this->expectExceptionObject(InvalidReport::because('An audit directory holds at most 1 reports.'));
        new ReportDirectory($this->directory, maxFiles: 1)->latest();
    }

    public function testAReportLargerThanItsLimitIsRefusedBeforeItIsRead(): void
    {
        $this->write('big.json', str_pad('{}', 11), 1_793_000_000);

        $this->expectExceptionObject(InvalidReport::because('A report of an audit directory holds at most 10 bytes: big.json is larger.'));
        new ReportDirectory($this->directory, maxBytes: 10)->latest();
    }

    public function testAReportAtItsLimitIsRead(): void
    {
        $this->write('page.json', str_pad('{}', 10), 1_793_000_000);

        self::assertCount(1, new ReportDirectory($this->directory, maxBytes: 10)->latest()->pages ?? []);

        $this->write('big.json', str_pad('{}', ReportReader::MAX_BYTES + 1), 1_793_000_000);
        $this->expectExceptionObject(InvalidReport::because('A report of an audit directory holds at most 5242880 bytes: big.json is larger.'));
        new ReportDirectory($this->directory)->latest();
    }

    public function testTheReaderKeepsItsOwnLimit(): void
    {
        $this->write('big.json', str_pad('{}', 64), 1_793_000_000);

        $this->expectExceptionObject(InvalidReport::because('A report holds at most 10 bytes.'));
        new ReportDirectory($this->directory, new ReportReader(10))->latest();
    }

    private function write(string $name, string $content, int $mtime): void
    {
        file_put_contents($this->directory.'/'.$name, $content);
        touch($this->directory.'/'.$name, $mtime);
    }
}
