<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\Exception\InvalidReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportReader::class)]
#[CoversClass(InvalidReport::class)]
final class ReportReaderTest extends TestCase
{
    public function testTheJsonOfAReportReadsBackToTheSameReport(): void
    {
        $report = new AccessibilityChecker()->checkHtml('<h2>Hours</h2><h4>Summer</h4><img src="/a.jpg"><p style="color: #aaa">Closed.</p>')->inLocale('fr');
        $json = (string) json_encode($report);
        $read = new ReportReader()->fromJson($json);

        self::assertSame($json, json_encode($read));
        self::assertSame('fr', $read->locale);
        self::assertSame(SuccessCriterion::NonTextContent, $read->ofRule('image-alt')[0]->criterion);
        self::assertSame(Severity::Error, $read->ofRule('image-alt')[0]->severity);
        self::assertSame(1, $read->findings[0]->location->line);
    }

    public function testAnEmptyReportPasses(): void
    {
        $report = new ReportReader()->fromArray([]);

        self::assertSame([], $report->results);
        self::assertSame([], $report->findings);
        self::assertSame('en', $report->locale);
    }

    public function testAFindingWithoutParametersNorLocationIsTheWholeContent(): void
    {
        $report = new ReportReader()->fromArray(['findings' => [['rule' => 'r', 'criterion' => '1.4.3', 'severity' => 'warning', 'messageKey' => 'k']]]);

        self::assertSame([], $report->findings[0]->message->parameters);
        self::assertSame('the content', $report->findings[0]->location->describe());
        self::assertSame(Severity::Warning, $report->findings[0]->severity);
    }

    public function testEveryFieldOfALocationIsRead(): void
    {
        $location = ['line' => 1, 'element' => 'p', 'snippet' => '<p>', 'block' => 'b1', 'blockType' => 'paragraph', 'pointer' => 'blocks[0].data.text'];
        $report = new ReportReader()->fromArray(['findings' => [['rule' => 'r', 'criterion' => '1.4.3', 'severity' => 'notice', 'messageKey' => 'k', 'parameters' => ['n' => 1, 'r' => 2.5, 's' => 'x'], 'location' => $location]]]);

        self::assertSame($location, $report->findings[0]->location->toArray());
        self::assertSame(['n' => 1, 'r' => 2.5, 's' => 'x'], $report->findings[0]->message->parameters);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('invalidReports')]
    public function testAnythingNotShapedAsAReportIsRefused(array $data, string $message): void
    {
        try {
            new ReportReader()->fromArray($data);
            self::fail('The report was accepted.');
        } catch (InvalidReport $invalidReport) {
            self::assertSame($message, $invalidReport->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidReports(): iterable
    {
        $rule = ['rule' => 'image-alt', 'criterion' => '1.1.1', 'severity' => 'error', 'findings' => 0];
        $finding = ['rule' => 'image-alt', 'criterion' => '1.1.1', 'severity' => 'error', 'messageKey' => 'k'];

        yield 'a locale as a number' => [['locale' => 3], 'The locale of a report is a language tag.'];
        yield 'an empty locale' => [['locale' => ''], 'The locale of a report is a language tag.'];
        yield 'rules as a map' => [['rules' => ['a' => $rule]], 'The rules of a report are a list of at most 50000 entries.'];
        yield 'rules as text' => [['rules' => 'none'], 'The rules of a report are a list of at most 50000 entries.'];
        yield 'too many findings' => [['findings' => array_fill(0, ReportReader::MAX_ENTRIES + 1, $finding)], 'The findings of a report are a list of at most 50000 entries.'];
        yield 'a rule as text' => [['rules' => ['image-alt']], 'A rule of a report is an object.'];
        yield 'a rule without name' => [['rules' => [[...$rule, 'rule' => '']]], 'The rule of a report entry is a string.'];
        yield 'a rule named by a number' => [['rules' => [[...$rule, 'rule' => 1]]], 'The rule of a report entry is a string.'];
        yield 'an unknown criterion' => [['rules' => [[...$rule, 'criterion' => '4.1.1']]], 'The criterion of a report entry is a success criterion of WCAG 2.2.'];
        yield 'an unknown severity' => [['rules' => [[...$rule, 'severity' => 'fatal']]], 'The severity of a report entry is error, warning or notice.'];
        yield 'negative findings' => [['rules' => [[...$rule, 'findings' => -1]]], 'The findings of a rule are counted from 0.'];
        yield 'findings as text' => [['rules' => [[...$rule, 'findings' => '1']]], 'The findings of a rule are counted from 0.'];
        yield 'a finding as text' => [['findings' => ['x']], 'A finding of a report is an object.'];
        yield 'parameters as text' => [['findings' => [[...$finding, 'parameters' => 'x']]], 'A parameters of a report is an object.'];
        yield 'a parameter without name' => [['findings' => [[...$finding, 'parameters' => ['x']]]], 'The parameters of a message are named strings and numbers.'];
        yield 'a parameter as a list' => [['findings' => [[...$finding, 'parameters' => ['x' => []]]]], 'The parameters of a message are named strings and numbers.'];
        yield 'a parameter as null' => [['findings' => [[...$finding, 'parameters' => ['x' => null]]]], 'The parameters of a message are named strings and numbers.'];
        yield 'no message key' => [['findings' => [[...$finding, 'messageKey' => null]]], 'The messageKey of a report entry is a string.'];
        yield 'a location as text' => [['findings' => [[...$finding, 'location' => 'line 1']]], 'A location of a report is an object.'];
        yield 'a line 0' => [['findings' => [[...$finding, 'location' => ['line' => 0]]]], 'The line of a location is counted from 1.'];
        yield 'a line as text' => [['findings' => [[...$finding, 'location' => ['line' => '1']]]], 'The line of a location is counted from 1.'];
        yield 'an element as a number' => [['findings' => [[...$finding, 'location' => ['element' => 1]]]], 'The element of a location is a string.'];
        yield 'a pointer as a number' => [['findings' => [[...$finding, 'location' => ['pointer' => 1]]]], 'The pointer of a location is a string.'];
    }

    public function testAReportAtItsLimitsIsRead(): void
    {
        $finding = ['rule' => 'image-alt', 'criterion' => '1.1.1', 'severity' => 'error', 'messageKey' => 'k'];

        self::assertCount(ReportReader::MAX_ENTRIES, new ReportReader()->fromArray(['findings' => array_fill(0, ReportReader::MAX_ENTRIES, $finding)])->findings);
    }

    public function testJsonBeyondItsLimitsIsRefused(): void
    {
        $messages = [];
        $deep = str_repeat('{"a":', ReportReader::MAX_DEPTH).'1'.str_repeat('}', ReportReader::MAX_DEPTH);

        foreach ([[10, '{'], [10, '"x"'], [10, str_pad('{}', 11)], [ReportReader::MAX_BYTES, $deep]] as [$bytes, $json]) {
            try {
                new ReportReader($bytes)->fromJson($json);
                self::fail('The JSON was accepted.');
            } catch (InvalidReport $invalidReport) {
                $messages[] = $invalidReport->getMessage();
            }
        }

        // The message of a JSON error is PHP's, which may say where the error is.
        self::assertCount(4, $messages);
        self::assertStringStartsWith('The report is not JSON: Syntax error', $messages[0]);
        self::assertSame('A report is a JSON object.', $messages[1]);
        self::assertSame('A report holds at most 10 bytes.', $messages[2]);
        self::assertStringStartsWith('The report is not JSON: Maximum stack depth exceeded', $messages[3]);
    }

    public function testJsonAtItsLimitsIsRead(): void
    {
        self::assertSame([], new ReportReader(10)->fromJson(str_pad('{}', 10))->findings);
        self::assertSame(5_242_880, ReportReader::MAX_BYTES);

        $deep = str_repeat('{"a":', ReportReader::MAX_DEPTH - 1).'1'.str_repeat('}', ReportReader::MAX_DEPTH - 1);
        self::assertSame([], new ReportReader()->fromJson($deep)->findings);
    }
}
