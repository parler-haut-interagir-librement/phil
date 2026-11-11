<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use Phil\Checkin\ScanOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScanOutcome::class)]
final class ScanOutcomeTest extends TestCase
{
    public function testOnlyAnAdmissionAndACheckOutChangeTheAttendance(): void
    {
        foreach (ScanOutcome::cases() as $outcome) {
            self::assertSame(\in_array($outcome, [ScanOutcome::Admitted, ScanOutcome::CheckedOut], true), $outcome->changesAttendance(), $outcome->value);
        }
    }
}
