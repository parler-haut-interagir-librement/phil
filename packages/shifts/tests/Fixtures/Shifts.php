<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Fixtures;

use DateTimeImmutable;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

/**
 * Shifts of a weekend convention, on Saturday 2026-11-28.
 */
final class Shifts
{
    private function __construct()
    {
    }

    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-28T'.$time.'Z');
    }

    public static function desk(int $capacity = 2, int $reserve = 1, AssignmentMode $mode = AssignmentMode::SelfSignUp, ?string $role = null, bool $approval = false, string $id = 'desk-am', string $from = '09:00:00', string $to = '12:00:00'): Shift
    {
        return new Shift($id, 'Info desk', ShiftKind::Fixed, self::at($from), self::at($to), null, $capacity, $mode, $reserve, $role, false, 100, $approval);
    }

    public static function cloakroom(): Shift
    {
        return new Shift('cloakroom', 'Cloakroom', ShiftKind::Duration, self::at('08:00:00'), self::at('20:00:00'), 120, 5, AssignmentMode::Interest, 0, null, true, 150);
    }
}
