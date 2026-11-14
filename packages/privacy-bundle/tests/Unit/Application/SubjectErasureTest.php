<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

#[CoversClass(SubjectErasure::class)]
#[CoversClass(ErasureIncomplete::class)]
#[CoversClass(MisconfiguredPrivacy::class)]
final class SubjectErasureTest extends TestCase
{
    public function testEveryModuleReportsInNameOrder(): void
    {
        $reports = new SubjectErasure([new RecordingEraser('newsletter'), new RecordingEraser('identity')])->erase(DataSubject::fromString('s-1'));

        self::assertSame(['identity', 'newsletter'], array_map(static fn (ErasureReport $report): string => $report->module->value, $reports));
    }

    /**
     * Stopping at the first failure would leave later modules untouched for no reason.
     */
    public function testAFailingModuleDoesNotStopTheOthers(): void
    {
        $failing = new RecordingEraser('billing', fails: true);
        $after = new RecordingEraser('identity');

        try {
            new SubjectErasure([$failing, $after])->erase(DataSubject::fromString('s-1'));
            self::fail('An incomplete erasure must not pass for a complete one.');
        } catch (ErasureIncomplete $erasureIncomplete) {
            self::assertSame(1, $after->calls);
            self::assertSame(['billing'], array_keys($erasureIncomplete->failures));
            self::assertSame(['identity'], array_map(static fn (ErasureReport $report): string => $report->module->value, $erasureIncomplete->completed));
            self::assertInstanceOf(RuntimeException::class, $erasureIncomplete->getPrevious());
            self::assertSame('Erasure failed in module(s) billing; retry it, erasers are idempotent.', $erasureIncomplete->getMessage());
            self::assertSame(0, $erasureIncomplete->getCode());
        }
    }

    public function testAReportFiledUnderAnotherModuleIsAFailure(): void
    {
        try {
            new SubjectErasure([new RecordingEraser('identity', reportsAs: 'billing')])->erase(DataSubject::fromString('s-1'));
            self::fail('A report under another name must not mark that module done.');
        } catch (ErasureIncomplete $erasureIncomplete) {
            self::assertSame([], $erasureIncomplete->completed);
            self::assertInstanceOf(UnexpectedValueException::class, $erasureIncomplete->failures['identity']);
        }
    }

    public function testTwoErasersUnderOneNameAreRefused(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);

        new SubjectErasure([new RecordingEraser('identity'), new RecordingEraser('identity')]);
    }
}
