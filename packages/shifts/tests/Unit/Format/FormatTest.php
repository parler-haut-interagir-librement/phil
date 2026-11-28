<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Unit\Format;

use DateTimeImmutable;
use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Format\Fields;
use Phil\Shifts\Format\RosterFormat;
use Phil\Shifts\Format\ShiftFormat;
use Phil\Shifts\Format\ShiftPlan;
use Phil\Shifts\Format\SwapFormat;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\Shifts\Tests\Fixtures\Shifts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShiftFormat::class)]
#[CoversClass(RosterFormat::class)]
#[CoversClass(SwapFormat::class)]
#[CoversClass(ShiftPlan::class)]
#[CoversClass(Fields::class)]
#[CoversClass(InvalidDocument::class)]
final class FormatTest extends TestCase
{
    public function testAShiftAtItsDefaultsWritesItsRequiredFields(): void
    {
        $shift = Shifts::desk(capacity: 1, reserve: 0);

        self::assertSame([
            'id' => 'desk-am',
            'title' => 'Info desk',
            'kind' => 'fixed',
            'starts_at' => '2026-11-28T09:00:00Z',
            'ends_at' => '2026-11-28T12:00:00Z',
            'minutes' => 180,
            'capacity' => 1,
            'mode' => 'self_sign_up',
            'reserve' => 0,
            'weight' => 100,
        ], ShiftFormat::toArray($shift));
        self::assertEquals($shift, ShiftFormat::fromArray(['id' => 'desk-am', 'title' => 'Info desk', 'kind' => 'fixed', 'starts_at' => '2026-11-28T04:00:00-05:00', 'ends_at' => '2026-11-28T12:00:00Z']));
    }

    public function testEveryFieldOfAShiftTravels(): void
    {
        $shift = new Shift('c', 'Cloakroom', ShiftKind::Duration, Shifts::at('08:00:00'), Shifts::at('20:00:00'), 120, 5, AssignmentMode::Interest, 3, 'FIRST_AID', true, 150, true, 'Hall B', "Coats\nand bags");
        $fields = ShiftFormat::toArray($shift);

        self::assertSame(['id' => 'c', 'title' => 'Cloakroom', 'kind' => 'duration', 'starts_at' => '2026-11-28T08:00:00Z', 'ends_at' => '2026-11-28T20:00:00Z', 'minutes' => 120, 'capacity' => 5, 'mode' => 'interest', 'reserve' => 3, 'required_role' => 'FIRST_AID', 'critical' => true, 'weight' => 150, 'swap_approval' => true, 'location' => 'Hall B', 'description' => "Coats\nand bags"], $fields);
        self::assertEquals($shift, ShiftFormat::fromArray($fields));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidShifts(): iterable
    {
        $valid = ['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2026-11-28T09:00:00Z', 'ends_at' => '2026-11-28T10:00:00Z'];

        yield 'not an object' => ['desk', '$: must be an object'];
        yield 'no kind' => [['kind' => 3] + $valid, '$.kind: must be a string'];
        yield 'unknown kind' => [['kind' => 'weekly'] + $valid, '$.kind: must be fixed, task or duration'];
        yield 'unknown mode' => [['mode' => 'lottery'] + $valid, '$.mode: must be self_sign_up, assigned or interest'];
        yield 'no instant' => [['starts_at' => '2026-11-28 09:00:00'] + $valid, '$.starts_at: must be an instant such as 2026-11-28T09:00:00Z'];
        yield 'impossible date' => [['starts_at' => '2026-02-30T09:00:00Z'] + $valid, '$.starts_at: must be an instant'];
        yield 'impossible hour' => [['ends_at' => '2026-11-28T25:00:00Z'] + $valid, '$.ends_at: must be an instant'];
        yield 'minute 60' => [['starts_at' => '2026-11-28T09:60:00Z'] + $valid, '$.starts_at: must be an instant'];
        yield 'second 60' => [['starts_at' => '2026-11-28T09:00:60Z'] + $valid, '$.starts_at: must be an instant'];
        yield 'hour 24' => [['starts_at' => '2026-11-28T24:00:00Z'] + $valid, '$.starts_at: must be an instant'];
        yield 'offset hour 24' => [['starts_at' => '2026-11-28T09:00:00+24:00'] + $valid, '$.starts_at: must be an instant'];
        yield 'offset minute 60' => [['starts_at' => '2026-11-28T09:00:00-01:60'] + $valid, '$.starts_at: must be an instant'];
        yield 'month 13' => [['starts_at' => '2026-13-01T09:00:00Z'] + $valid, '$.starts_at: must be an instant'];
        yield 'trailing text' => [['ends_at' => '2026-11-28T10:00:00Zx'] + $valid, '$.ends_at: must be an instant'];
        yield 'leading text' => [['ends_at' => 'x2026-11-28T10:00:00Z'] + $valid, '$.ends_at: must be an instant'];
        yield 'capacity text' => [['capacity' => '2'] + $valid, '$.capacity: must be a whole number'];
        yield 'minutes text' => [['minutes' => '60'] + $valid, '$.minutes: must be a whole number'];
        yield 'critical text' => [['critical' => 'yes'] + $valid, '$.critical: must be true or false'];
        yield 'title number' => [['title' => 1] + $valid, '$.title: must be a string'];
        yield 'role number' => [['required_role' => 1] + $valid, '$.required_role: must be a string'];
        yield 'out of bounds' => [['capacity' => 0] + $valid, '$: A shift has 1 to 1,000 places.'];
    }

    #[DataProvider('invalidShifts')]
    public function testAShiftOfTheWrongShape(mixed $data, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShiftFormat::fromArray($data);
    }

    public function testTheBoundsErrorKeepsItsCause(): void
    {
        try {
            ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2026-11-28T09:00:00Z', 'ends_at' => '2026-11-28T10:00:00Z', 'weight' => 1], '$.shifts[3]');
            self::fail('The shift is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('$.shifts[3]: The weight of a shift is 10 to 1,000 percent.', $invalidDocument->getMessage());
            self::assertNotNull($invalidDocument->getPrevious());
        }
    }

    public function testAnInstantWithAnOffsetIsReadInUtc(): void
    {
        $shift = ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2026-11-28T09:00:00+01:30', 'ends_at' => '2026-11-28T10:00:00+01:30']);

        self::assertSame('2026-11-28T07:30:00+00:00', $shift->startsAt->format(DateTimeImmutable::ATOM));
        self::assertSame('UTC', $shift->startsAt->getTimezone()->getName());

        $edges = ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2026-11-28T23:59:59-05:30', 'ends_at' => '2026-11-30T23:59:59+23:59']);
        self::assertSame('2026-11-29T05:29:59+00:00', $edges->startsAt->format(DateTimeImmutable::ATOM));
        self::assertSame('2026-11-30T00:00:59+00:00', $edges->endsAt->format(DateTimeImmutable::ATOM));
        self::assertSame('2024-02-29T00:00:00+00:00', ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2024-02-29T00:00:00Z', 'ends_at' => '2024-02-29T01:00:00Z'])->startsAt->format(DateTimeImmutable::ATOM));
    }

    public function testARosterTravels(): void
    {
        $shift = Shifts::desk();
        $roster = new Roster($shift, [new Place('ada', PlaceStatus::Confirmed, Shifts::at('06:00:00'), 'grace'), new Place('bob', PlaceStatus::Reserve, Shifts::at('06:30:00'))]);
        $fields = RosterFormat::toArray($roster);

        self::assertSame([
            ['volunteer' => 'ada', 'status' => 'confirmed', 'at' => '2026-11-28T06:00:00Z', 'assigned_by' => 'grace'],
            ['volunteer' => 'bob', 'status' => 'reserve', 'at' => '2026-11-28T06:30:00Z'],
        ], $fields);
        self::assertEquals($roster, RosterFormat::fromArray($shift, $fields));
        self::assertSame([], RosterFormat::fromArray($shift, [])->places);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidRosters(): iterable
    {
        yield 'not a list' => [['a' => []], '$.places: must be a list'];
        yield 'not an object' => [['ada'], '$[0]: must be an object'];
        yield 'unknown status' => [[['volunteer' => 'ada', 'status' => 'maybe', 'at' => '2026-11-28T06:00:00Z']], '$[0].status: must be confirmed, reserve or interested'];
        yield 'bad volunteer' => [[['volunteer' => ' ada', 'status' => 'confirmed', 'at' => '2026-11-28T06:00:00Z']], '$[0]: The volunteer is 1 to 191'];
        yield 'twice' => [[['volunteer' => 'ada', 'status' => 'confirmed', 'at' => '2026-11-28T06:00:00Z'], ['volunteer' => 'ada', 'status' => 'reserve', 'at' => '2026-11-28T06:00:00Z']], '$: A volunteer stands once on a roster.'];
        yield 'too many' => [array_fill(0, 2101, []), '$.places: holds 2100 entries at most'];
    }

    #[DataProvider('invalidRosters')]
    public function testARosterOfTheWrongShape(mixed $data, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        RosterFormat::fromArray(Shifts::desk(), $data);
    }

    public function testAnOfferTravels(): void
    {
        $open = new SwapOffer('s', 'desk-am', 'ada', Shifts::at('06:00:00'));
        $done = new SwapOffer('s', 'desk-am', 'ada', Shifts::at('06:00:00'), 'Sick', SwapStatus::Completed, 'bob', 'grace', Shifts::at('07:00:00'));

        self::assertSame(['id' => 's', 'shift_id' => 'desk-am', 'offered_by' => 'ada', 'created_at' => '2026-11-28T06:00:00Z', 'status' => 'open'], SwapFormat::toArray($open));
        self::assertSame(['id' => 's', 'shift_id' => 'desk-am', 'offered_by' => 'ada', 'created_at' => '2026-11-28T06:00:00Z', 'note' => 'Sick', 'status' => 'completed', 'taken_by' => 'bob', 'decided_by' => 'grace', 'decided_at' => '2026-11-28T07:00:00Z'], SwapFormat::toArray($done));
        self::assertEquals($open, SwapFormat::fromArray(SwapFormat::toArray($open)));
        self::assertEquals($done, SwapFormat::fromArray(SwapFormat::toArray($done)));

        try {
            SwapFormat::fromArray(['status' => 'lost'] + SwapFormat::toArray($open));
            self::fail('The offer is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('$.status: must be open, pending, completed or cancelled', $invalidDocument->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('$: An open offer is taken by nobody');
        SwapFormat::fromArray(['taken_by' => 'bob'] + SwapFormat::toArray($open));
    }

    public function testAnInstantEndsWhereItsTextEnds(): void
    {
        foreach (["2026-11-28T09:00:00Z\n", "2026-11-28T09:00:00+01:00\n", '2026-11-28T24:00:00Z', '2026-11-28T09:60:00Z', '2026-11-28T09:00:60Z', '2026-11-28T09:00:00+24:00', '2026-11-28T09:00:00+01:60', '2026-02-30T09:00:00Z'] as $instant) {
            try {
                ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => $instant, 'ends_at' => '2026-11-29T10:00:00Z']);
                self::fail('The instant is refused.');
            } catch (InvalidDocument $invalidDocument) {
                self::assertSame('$.starts_at: must be an instant such as 2026-11-28T09:00:00Z', $invalidDocument->getMessage());
            }
        }

        $shift = ShiftFormat::fromArray(['id' => 'd', 'title' => 'T', 'kind' => 'fixed', 'starts_at' => '2026-02-28T23:30:15-00:30', 'ends_at' => '2026-03-01T01:00:00Z']);
        self::assertSame('2026-03-01T00:00:15+00:00', $shift->startsAt->format(DateTimeImmutable::ATOM));
    }

    public function testADocumentErrorHasNoCode(): void
    {
        self::assertSame(0, InvalidDocument::because('Reason.')->getCode());
        self::assertSame(0, InvalidDocument::at('$.a', 'Reason.')->getCode());
    }

    public function testAPlanWritesItsSlashesAsTheyAre(): void
    {
        $plan = new ShiftPlan([new Shift('d', 'Desk', ShiftKind::Fixed, Shifts::at('09:00:00'), Shifts::at('10:00:00'), null, 1, AssignmentMode::SelfSignUp, 0, null, false, 100, false, 'Hall A/B')]);

        self::assertStringContainsString('"location": "Hall A/B"', $plan->toJson());
        self::assertStringNotContainsString('\\/', $plan->toJson());
    }

    public function testAPlanIsAsDeepAsTheLimitOfTheReader(): void
    {
        self::assertSame([], ShiftPlan::fromJson('{"format":"phil-shifts/1","x":'.str_repeat('[', 14).str_repeat(']', 14).'}')->shifts);

        $this->expectExceptionMessageIsOrContains('A plan of shifts is a JSON document.');
        ShiftPlan::fromJson('{"format":"phil-shifts/1","x":'.str_repeat('[', 15).str_repeat(']', 15).'}');
    }

    public function testAPlanReadsAndWritesTheSame(): void
    {
        $json = '{"format":"phil-shifts/1","shifts":[{"id":"desk-am","title":"Info desk","kind":"fixed","starts_at":"2026-11-28T09:00:00-05:00","ends_at":"2026-11-28T12:00:00-05:00","capacity":2},{"id":"c","title":"Café","kind":"task","starts_at":"2026-11-28T09:00:00Z","ends_at":"2026-11-28T12:00:00Z","minutes":30}]}';
        $plan = ShiftPlan::fromJson($json);

        self::assertSame(['desk-am', 'c'], array_map(static fn (Shift $shift): string => $shift->id, $plan->shifts));
        self::assertSame(2, $plan->shifts[0]->capacity);
        self::assertSame($plan->toJson(), ShiftPlan::fromJson($plan->toJson())->toJson());
        self::assertStringContainsString('"title": "Café"', $plan->toJson());
        self::assertStringContainsString('"starts_at": "2026-11-28T14:00:00Z"', $plan->toJson());
        self::assertSame([], ShiftPlan::fromJson('{"format":"phil-shifts/1"}')->shifts);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPlans(): iterable
    {
        yield 'not JSON' => ['{', 'A plan of shifts is a JSON document.'];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), 'A plan of shifts is a JSON document.'];
        yield 'not an object' => ['"plan"', '$: must be an object'];
        yield 'other format' => ['{"format":"phil-shifts/2"}', '$.format: must be phil-shifts/1'];
        yield 'shifts not a list' => ['{"format":"phil-shifts/1","shifts":{"a":1}}', '$.shifts: must be a list'];
        yield 'bad shift' => ['{"format":"phil-shifts/1","shifts":[{"id":"d"}]}', '$.shifts[0].kind: must be a string'];
        yield 'twice' => ['{"format":"phil-shifts/1","shifts":[{"id":"d","title":"T","kind":"fixed","starts_at":"2026-11-28T09:00:00Z","ends_at":"2026-11-28T10:00:00Z"},{"id":"d","title":"T","kind":"fixed","starts_at":"2026-11-28T09:00:00Z","ends_at":"2026-11-28T10:00:00Z"}]}', '$.shifts[1].id: names a shift already in the plan'];
        yield 'too large' => [str_pad('{"format":"phil-shifts/1"}', ShiftPlan::MAX_BYTES + 1), 'A plan of shifts is 1 MiB at most.'];
        yield 'too many' => ['{"format":"phil-shifts/1","shifts":['.implode(',', array_fill(0, 2001, '{}')).']}', '$.shifts: holds 2000 entries at most'];
    }

    #[DataProvider('invalidPlans')]
    public function testAPlanOfTheWrongShape(string $json, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShiftPlan::fromJson($json);
    }

    public function testAPlanAtItsBounds(): void
    {
        self::assertSame([], ShiftPlan::fromJson(str_pad('{"format":"phil-shifts/1"}', ShiftPlan::MAX_BYTES))->shifts);
        self::assertSame([], ShiftPlan::fromJson('{"format":"phil-shifts/1","shifts":[],"x":'.str_repeat('[', 13).str_repeat(']', 13).'}')->shifts);

        // 2,000 shifts are within bounds: the first is read, and refused for its own fields.
        $this->expectExceptionMessageIsOrContains('$.shifts[0].kind');
        ShiftPlan::fromJson('{"format":"phil-shifts/1","shifts":['.implode(',', array_fill(0, 2000, '{}')).']}');
    }
}
