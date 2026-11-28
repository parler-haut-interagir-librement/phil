<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Unit\Hours;

use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Tests\Fixtures\Shifts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServedHours::class)]
final class ServedHoursTest extends TestCase
{
    public function testTheMinutesOfTheShiftsOver(): void
    {
        $at = Shifts::at('06:00:00');
        $rosters = [
            new Roster(Shifts::desk(), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('bob', PlaceStatus::Reserve, $at)]),
            new Roster(Shifts::cloakroom(), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('bob', PlaceStatus::Confirmed, $at)]),
            new Roster(Shifts::desk(id: 'pm', from: '13:00:00', to: '14:00:00'), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('cy', PlaceStatus::Interested, $at)]),
        ];

        $ada = ServedHours::of('ada', $rosters, Shifts::at('14:00:00'));
        self::assertSame('ada', $ada->volunteer);
        self::assertSame(240, $ada->minutes);
        self::assertSame(240, $ada->weightedMinutes);
        self::assertSame(['desk-am', 'pm'], $ada->shifts);

        $later = ServedHours::of('ada', $rosters, Shifts::at('20:00:00'));
        self::assertSame(360, $later->minutes);
        self::assertSame(420, $later->weightedMinutes);
        self::assertSame(['desk-am', 'cloakroom', 'pm'], $later->shifts);

        self::assertSame(0, ServedHours::of('bob', $rosters, Shifts::at('14:00:00'))->minutes);
        self::assertSame(0, ServedHours::of('cy', $rosters, Shifts::at('20:00:00'))->weightedMinutes);
        self::assertSame(['ada' => 360, 'bob' => 120], ServedHours::held($rosters));
        self::assertSame([], ServedHours::held([]));
    }
}
