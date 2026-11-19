<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTrackingBundle\Application\UndeliveredAlerter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(UndeliveredAlerter::class)]
final class UndeliveredAlerterTest extends TestCase
{
    public function testEachAlertBecomesAWarningThatNamesTheGroupOnly(): void
    {
        $logger = new RecordingLogger();
        $alerter = new UndeliveredAlerter($logger);
        $group = new ErrorGroup(str_repeat('a', 64), 'RuntimeException: jane@example.com failed', null, GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable());

        $alerter->alert($group, Occurrence::First);
        $alerter->alert($group, Occurrence::Regression);

        self::assertSame([
            [LogLevel::WARNING, UndeliveredAlerter::MESSAGE, ['fingerprint' => str_repeat('a', 64), 'occurrence' => 'first']],
            [LogLevel::WARNING, UndeliveredAlerter::MESSAGE, ['fingerprint' => str_repeat('a', 64), 'occurrence' => 'regression']],
        ], $logger->records);
        self::assertStringNotContainsString('jane', serialize($logger->records));
    }

    public function testWithoutALoggerNothingHappens(): void
    {
        $this->expectNotToPerformAssertions();

        new UndeliveredAlerter()->alert(new ErrorGroup(str_repeat('b', 64), 'E', null, GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable()), Occurrence::First);
    }
}
