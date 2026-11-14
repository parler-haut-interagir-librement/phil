<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErasureReport::class)]
final class ErasureReportTest extends TestCase
{
    public function testItKeepsTheEffectOfEachStore(): void
    {
        $report = ErasureReport::of('identity', ['accounts' => ErasureEffect::Pseudonymised, 'emails' => ErasureEffect::Deleted]);

        self::assertSame('identity', $report->module->value);
        self::assertSame(ErasureEffect::Deleted, $report->effects['emails']);
    }

    /**
     * An empty report is indistinguishable from a module that forgot a store.
     */
    public function testAReportNamingNoStoreIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('names no store');

        ErasureReport::of('identity', []);
    }

    public function testAStoreWithoutANameIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        ErasureReport::of('identity', [' ' => ErasureEffect::Deleted]);
    }
}
