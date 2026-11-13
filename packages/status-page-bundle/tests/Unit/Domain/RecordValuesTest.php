<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Domain;

use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\IncidentUpdate;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Domain\ValueObject\Text;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Component::class)]
#[CoversClass(ComponentState::class)]
#[CoversClass(Incident::class)]
#[CoversClass(IncidentUpdate::class)]
#[CoversClass(ScheduledMaintenance::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidStatusRecord::class)]
final class RecordValuesTest extends TestCase
{
    public function testAComponentKeepsItsTextsWithTheirSpacesCollapsed(): void
    {
        $component = new Component('api-2_x', "  The\n API  ", ' All  endpoints ', ' Platform ');

        self::assertSame('api-2_x', $component->id);
        self::assertSame('The API', $component->name);
        self::assertSame('All endpoints', $component->description);
        self::assertSame('Platform', $component->group);
        self::assertNull(new Component('a', 'A')->description);
        self::assertNull(new Component('a', 'A')->group);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Api'];
        yield 'leading dash' => ['-api'];
        yield 'space' => ['my api'];
        yield 'trailing new line' => ["api\n"];
        yield '65 characters' => [str_repeat('a', 65)];
    }

    #[DataProvider('invalidIds')]
    public function testAnIdIsRefused(string $id): void
    {
        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains('The component id is 1 to 64 lower-case letters, digits, "-" or "_", starting with a letter or a digit.');

        new Component($id, 'Name');
    }

    public function testAnIdOf64CharactersIsKept(): void
    {
        self::assertSame(str_repeat('a', 64), new Component(str_repeat('a', 64), 'A')->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLines(): iterable
    {
        yield 'blank' => ["  \n "];
        yield '121 characters' => [str_repeat('é', 121)];
        yield 'invalid UTF-8' => ["\xC3\x28"];
        yield 'control character' => ["A\x01B"];
        yield 'delete character' => ["A\x7FB"];
    }

    #[DataProvider('invalidLines')]
    public function testANameIsRefused(string $name): void
    {
        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains('The component name is one line of 1 to 120 characters of UTF-8, without control characters.');

        new Component('a', $name);
    }

    public function testANameOf120CharactersIsKept(): void
    {
        self::assertSame(str_repeat('é', 120), new Component('a', str_repeat('é', 120))->name);
    }

    public function testTheDescriptionAndTheGroupHaveTheirOwnLengths(): void
    {
        self::assertSame(300, mb_strlen((string) new Component('a', 'A', str_repeat('d', 300))->description));
        self::assertSame(120, mb_strlen((string) new Component('a', 'A', null, str_repeat('g', 120))->group));

        try {
            new Component('a', 'A', str_repeat('d', 301));
            self::fail('A description of 301 characters is refused.');
        } catch (InvalidStatusRecord $invalidStatusRecord) {
            self::assertStringContainsString('component description is one line of 1 to 300', $invalidStatusRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('component group is one line of 1 to 120');
        new Component('a', 'A', null, str_repeat('g', 121));
    }

    public function testAMessageKeepsItsLinesTrimmedAndNormalised(): void
    {
        $update = new IncidentUpdate(Fixtures::now(), IncidentState::Identified, "  First line\r\n\tSecond line\rThird  ");

        self::assertSame("First line\n\tSecond line\nThird", $update->message);
        self::assertSame(IncidentState::Identified, $update->state);
        self::assertEquals(Fixtures::now(), $update->at);
        self::assertSame(4000, mb_strlen(new IncidentUpdate(Fixtures::now(), IncidentState::Resolved, str_repeat('m', 4000))->message));
        self::assertSame(4000, mb_strlen(new IncidentUpdate(Fixtures::now(), IncidentState::Resolved, str_repeat('é', 4000))->message));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'blank' => [" \n "];
        yield '4001 characters' => [str_repeat('m', 4001)];
        yield 'invalid UTF-8' => ["\xFF"];
        yield 'null byte' => ["a\x00b"];
        yield 'escape' => ["a\x1Bb"];
        yield 'vertical tab' => ["a\x0Bb"];
        yield 'delete' => ["a\x7Fb"];
    }

    #[DataProvider('invalidMessages')]
    public function testAMessageIsRefused(string $message): void
    {
        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains('The message of an update is 1 to 4000 characters of UTF-8, without control characters.');

        new IncidentUpdate(Fixtures::now(), IncidentState::Investigating, $message);
    }

    public function testAnIncidentSortsItsUpdatesNewestFirst(): void
    {
        $first = new IncidentUpdate(Fixtures::at('-2 hours'), IncidentState::Investigating, 'Looking.');
        $second = new IncidentUpdate(Fixtures::at('-1 hour'), IncidentState::Monitoring, 'Fixed, watching.');
        $incident = new Incident('slow', ' Slow  pages ', ComponentStatus::Degraded, ['website', 'api'], [$first, $second]);

        self::assertSame('slow', $incident->id);
        self::assertSame('Slow pages', $incident->title);
        self::assertSame(['website', 'api'], $incident->components);
        self::assertSame([$second, $first], $incident->updates);
        self::assertSame(IncidentState::Monitoring, $incident->state());
        self::assertFalse($incident->isResolved());
        self::assertNull($incident->resolvedAt());
        self::assertEquals(Fixtures::at('-2 hours'), $incident->startedAt());
        self::assertEquals(Fixtures::at('-1 hour'), $incident->updatedAt());
        self::assertTrue($incident->affects('api'));
        self::assertFalse($incident->affects('sign-in'));
        self::assertTrue($incident->isActiveAt(Fixtures::now()));
        self::assertTrue($incident->isActiveAt(Fixtures::at('-2 hours')));
        self::assertFalse($incident->isActiveAt(Fixtures::at('-3 hours')));
    }

    public function testAResolvedIncidentIsOverAtItsLastUpdate(): void
    {
        $incident = Fixtures::incident('down', ComponentStatus::MajorOutage, ['api'], '-3 hours', '-1 hour');

        self::assertTrue($incident->isResolved());
        self::assertEquals(Fixtures::at('-1 hour'), $incident->resolvedAt());
        self::assertFalse($incident->isActiveAt(Fixtures::now()));
    }

    public function testAnIncidentWithoutUpdateOrWithTooManyIsRefused(): void
    {
        $update = new IncidentUpdate(Fixtures::now(), IncidentState::Investigating, 'Looking.');
        self::assertCount(100, new Incident('a', 'A', ComponentStatus::Degraded, [], array_fill(0, 100, $update))->updates);

        foreach ([[], array_fill(0, 101, $update)] as $updates) {
            try {
                new Incident('a', 'A', ComponentStatus::Degraded, [], $updates);
                self::fail('An incident has 1 to 100 updates.');
            } catch (InvalidStatusRecord $invalid) {
                self::assertSame('An incident has 1 to 100 updates.', $invalid->getMessage());
            }
        }
    }

    public function testTheImpactOfAnIncidentIsAnOutage(): void
    {
        $update = new IncidentUpdate(Fixtures::now(), IncidentState::Investigating, 'Looking.');

        foreach ([ComponentStatus::Operational, ComponentStatus::Maintenance] as $impact) {
            try {
                new Incident('a', 'A', $impact, [], [$update]);
                self::fail('An incident is degraded or an outage.');
            } catch (InvalidStatusRecord $invalid) {
                self::assertStringContainsString('The impact of an incident is', $invalid->getMessage());
            }
        }
    }

    public function testAComponentIsNamedOnceByAnIncident(): void
    {
        $this->expectExceptionMessageIsOrContains('Each component of an incident is given once.');

        new Incident('a', 'A', ComponentStatus::Degraded, ['api', 'api'], [new IncidentUpdate(Fixtures::now(), IncidentState::Investigating, 'Looking.')]);
    }

    public function testAMaintenanceIsUpcomingThenInProgressThenOver(): void
    {
        $maintenance = new ScheduledMaintenance('upgrade', ' Upgrade ', ['api'], Fixtures::at('+1 hour'), Fixtures::at('+2 hours'), "  Database\nupgrade ");

        self::assertSame('upgrade', $maintenance->id);
        self::assertSame('Upgrade', $maintenance->title);
        self::assertSame("Database\nupgrade", $maintenance->description);
        self::assertSame(['api'], $maintenance->components);
        self::assertTrue($maintenance->isUpcomingAt(Fixtures::now()));
        self::assertFalse($maintenance->isInProgressAt(Fixtures::now()));
        self::assertFalse($maintenance->isUpcomingAt(Fixtures::at('+1 hour')));
        self::assertTrue($maintenance->isInProgressAt(Fixtures::at('+1 hour')));
        self::assertTrue($maintenance->isInProgressAt(Fixtures::at('+119 minutes')));
        self::assertFalse($maintenance->isInProgressAt(Fixtures::at('+2 hours')));
        self::assertFalse($maintenance->isUpcomingAt(Fixtures::at('+2 hours')));
        self::assertTrue($maintenance->affects('api'));
        self::assertFalse($maintenance->affects('website'));
        self::assertNull(new ScheduledMaintenance('a', 'A', [], Fixtures::now(), Fixtures::at('+1 second'))->description);
    }

    public function testAMaintenanceEndsAfterItStarts(): void
    {
        $this->expectExceptionMessageIsOrContains('A maintenance ends after it starts.');

        new ScheduledMaintenance('a', 'A', [], Fixtures::now(), Fixtures::now());
    }

    public function testAComponentStateHoldsItsComponentAndStatus(): void
    {
        $component = new Component('a', 'A');
        $state = new ComponentState($component, ComponentStatus::Degraded);

        self::assertSame($component, $state->component);
        self::assertSame(ComponentStatus::Degraded, $state->status);
    }

    public function testALineIsOf160CharactersByDefault(): void
    {
        self::assertSame(160, mb_strlen(Text::line(str_repeat('t', 160), 'title')));

        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains('The title is one line of 1 to 160 characters of UTF-8');
        Text::line(str_repeat('t', 161), 'title');
    }
}
