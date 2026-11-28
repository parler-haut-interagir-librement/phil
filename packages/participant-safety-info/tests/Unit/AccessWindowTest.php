<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\AccessWindow;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessWindow::class)]
final class AccessWindowTest extends TestCase
{
    public function testItIsOpenFromItsOpeningToBeforeItsClosing(): void
    {
        $window = new AccessWindow(new DateTimeImmutable('2026-12-01 00:00'), new DateTimeImmutable('2026-12-05 00:00'));

        self::assertFalse($window->isOpenAt(new DateTimeImmutable('2026-11-30 23:59:59')));
        self::assertTrue($window->isOpenAt(new DateTimeImmutable('2026-12-01 00:00')));
        self::assertTrue($window->isOpenAt(new DateTimeImmutable('2026-12-04 23:59:59')));
        self::assertFalse($window->isOpenAt(new DateTimeImmutable('2026-12-05 00:00')));
        self::assertFalse($window->hasClosedAt(new DateTimeImmutable('2026-12-04 23:59:59')));
        self::assertTrue($window->hasClosedAt(new DateTimeImmutable('2026-12-05 00:00')));
    }

    public function testAWindowClosesAfterItOpens(): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('An access window closes after it opens.');

        new AccessWindow(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-01'));
    }
}
