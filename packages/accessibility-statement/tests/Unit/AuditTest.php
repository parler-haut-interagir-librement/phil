<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditResult::class)]
#[CoversClass(AuditedPage::class)]
final class AuditTest extends TestCase
{
    public function testAnAuditHoldsItsPagesUpToItsLimit(): void
    {
        $page = new AuditedPage('/', new Report());
        $audit = new AuditResult(new DateTimeImmutable('2026-11-08'), array_fill(0, AuditResult::MAX_PAGES, $page));

        self::assertCount(AuditResult::MAX_PAGES, $audit->pages);
        self::assertSame(EvaluationMethod::Automated, $audit->method);
        self::assertNull($audit->reportUrl);

        $this->expectExceptionMessageIsOrContains('An audit holds at most 10000 pages.');
        new AuditResult(new DateTimeImmutable('2026-11-08'), array_fill(0, AuditResult::MAX_PAGES + 1, $page));
    }

    public function testAPageIsNamed(): void
    {
        self::assertSame(str_repeat('a', AuditedPage::MAX_PAGE_LENGTH), new AuditedPage(str_repeat('a', AuditedPage::MAX_PAGE_LENGTH), new Report())->page);

        foreach (['', str_repeat('a', AuditedPage::MAX_PAGE_LENGTH + 1)] as $name) {
            try {
                new AuditedPage($name, new Report());
                self::fail('The page was accepted.');
            } catch (InvalidStatement $invalidStatement) {
                self::assertSame('An audited page is named in 1 to 2048 bytes.', $invalidStatement->getMessage());
            }
        }
    }
}
