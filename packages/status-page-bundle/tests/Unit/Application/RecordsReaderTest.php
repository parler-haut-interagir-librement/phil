<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Application;

use Phil\StatusPageBundle\Application\Records;
use Phil\StatusPageBundle\Application\RecordsReader;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordsReader::class)]
#[CoversClass(Records::class)]
final class RecordsReaderTest extends TestCase
{
    public function testItReadsIncidentsAndMaintenances(): void
    {
        $records = new RecordsReader()->read([
            'incidents' => [[
                'id' => 'slow',
                'title' => 'Slow pages',
                'impact' => 'partial_outage',
                'components' => ['website'],
                'updates' => [
                    ['at' => '2026-11-12T10:00:00+02:00', 'state' => 'investigating', 'message' => 'Looking.'],
                    ['at' => '2026-11-12T09:30:00Z', 'state' => 'resolved', 'message' => 'Fixed.'],
                ],
            ]],
            'maintenances' => [[
                'id' => 'upgrade',
                'title' => 'Upgrade',
                'description' => 'Database upgrade.',
                'components' => ['api', 'website'],
                'starts_at' => '2026-11-20T02:00:00-05:00',
                'ends_at' => '2026-11-20T03:00:00-05:00',
            ]],
        ]);

        self::assertCount(1, $records->incidents);
        $incident = $records->incidents[0];
        self::assertSame('slow', $incident->id);
        self::assertSame('Slow pages', $incident->title);
        self::assertSame(ComponentStatus::PartialOutage, $incident->impact);
        self::assertSame(['website'], $incident->components);
        self::assertSame(IncidentState::Resolved, $incident->state());
        self::assertSame('2026-11-12T09:30:00+00:00', $incident->updates[0]->at->format(\DATE_ATOM));
        self::assertSame('Fixed.', $incident->updates[0]->message);
        self::assertSame('2026-11-12T08:00:00+00:00', $incident->updates[1]->at->format(\DATE_ATOM));
        self::assertSame(IncidentState::Investigating, $incident->updates[1]->state);

        self::assertCount(1, $records->maintenances);
        $maintenance = $records->maintenances[0];
        self::assertSame('upgrade', $maintenance->id);
        self::assertSame('Upgrade', $maintenance->title);
        self::assertSame('Database upgrade.', $maintenance->description);
        self::assertSame(['api', 'website'], $maintenance->components);
        self::assertSame('2026-11-20T07:00:00+00:00', $maintenance->startsAt->format(\DATE_ATOM));
        self::assertSame('2026-11-20T08:00:00+00:00', $maintenance->endsAt->format(\DATE_ATOM));
    }

    public function testNothingReadsAsNoRecord(): void
    {
        $records = new RecordsReader()->read([]);

        self::assertSame([], $records->incidents);
        self::assertSame([], $records->maintenances);
    }

    public function testAMaintenanceWithoutDescriptionOrComponentsIsRead(): void
    {
        $maintenance = new RecordsReader()->read(['maintenances' => [['id' => 'm', 'title' => 'M', 'starts_at' => '2026-11-20T02:00:00Z', 'ends_at' => '2026-11-20T03:00:00Z']]])->maintenances[0];

        self::assertNull($maintenance->description);
        self::assertSame([], $maintenance->components);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function refused(): iterable
    {
        $update = ['at' => '2026-11-12T10:00:00Z', 'state' => 'investigating', 'message' => 'Looking.'];
        $incident = ['id' => 'a', 'title' => 'A', 'impact' => 'degraded', 'components' => [], 'updates' => [$update]];
        $maintenance = ['id' => 'm', 'title' => 'M', 'components' => [], 'starts_at' => '2026-11-20T02:00:00Z', 'ends_at' => '2026-11-20T03:00:00Z'];

        yield 'unknown key' => [['incident' => []], 'The keys of records are incidents, maintenances.'];
        yield 'incidents not a list' => [['incidents' => ['a' => $incident]], 'The incidents of incidents are a list.'];
        yield 'incidents a string' => [['incidents' => 'none'], 'The incidents of incidents are a list.'];
        yield 'too many incidents' => [['incidents' => array_fill(0, 501, $incident)], 'The incidents of incidents are 500 at most.'];
        yield 'incident not a map' => [['incidents' => [$incident, 'x']], 'Each of the incidents is a map; 2 is not.'];
        yield 'unknown incident key' => [['incidents' => [[...$incident, 'severity' => 1]]], 'The keys of incident 1 are id, title, impact, components, updates.'];
        yield 'id not a string' => [['incidents' => [[...$incident, 'id' => 1]]], 'The id of incident 1 is a string.'];
        yield 'missing title' => [['incidents' => [array_diff_key($incident, ['title' => true])]], 'The title of incident 1 is a string.'];
        yield 'unknown impact' => [['incidents' => [[...$incident, 'impact' => 'bad']]], 'The impact of incident 1 is "degraded", "partial_outage" or "major_outage".'];
        yield 'components not strings' => [['incidents' => [[...$incident, 'components' => [1]]]], 'The components of incident 1 are strings.'];
        yield 'components not a list' => [['incidents' => [[...$incident, 'components' => 'api']]], 'The components of incident 1 are a list.'];
        yield 'too many components' => [['incidents' => [[...$incident, 'components' => array_fill(0, 501, 'a')]]], 'The components of incident 1 are 500 at most.'];
        yield 'too many updates' => [['incidents' => [[...$incident, 'updates' => array_fill(0, 101, $update)]]], 'The updates of incident 1 are 100 at most.'];
        yield 'update not a map' => [['incidents' => [[...$incident, 'updates' => [$update, 'x']]]], 'Update 2 of incident 1 is a map.'];
        yield 'unknown update key' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'by' => 'me']]]]], 'The keys of update 1 of incident 1 are at, state, message.'];
        yield 'unknown state' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'state' => 'done']]]]], 'The state of update 1 of incident 1 is "investigating", "identified", "monitoring" or "resolved".'];
        yield 'message not a string' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'message' => null]]]]], 'The message of update 1 of incident 1 is a string.'];
        yield 'date without offset' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => '2026-11-12T10:00:00']]]]], 'The at of update 1 of incident 1 is a date and time with its offset, such as 2026-11-12T10:00:00+00:00.'];
        yield 'date with a prefix' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => 'x2026-11-12T10:00:00Z']]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'date with a suffix' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => "2026-11-12T10:00:00Z\n"]]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'date the calendar does not have' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => '2026-02-30T10:00:00Z']]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'hour the clock does not have' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => '2026-02-10T24:00:00Z']]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'offset with a third digit' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => '2026-11-12T10:00:00+00:000']]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'offset with seconds' => [['incidents' => [[...$incident, 'updates' => [[...$update, 'at' => '2026-11-12T10:00:00+00:00:00']]]]], 'The at of update 1 of incident 1 is a date and time'];
        yield 'same incident twice' => [['incidents' => [$incident, $incident]], 'Each incident id is given once.'];
        yield 'maintenances not a list' => [['maintenances' => 3], 'The maintenances of maintenances are a list.'];
        yield 'unknown maintenance key' => [['maintenances' => [[...$maintenance, 'impact' => 'x']]], 'The keys of maintenance 1 are id, title, description, components, starts_at, ends_at.'];
        yield 'description not a string' => [['maintenances' => [[...$maintenance, 'description' => 3]]], 'The description of maintenance 1 is a string.'];
        yield 'missing start' => [['maintenances' => [array_diff_key($maintenance, ['starts_at' => true])]], 'The starts_at of maintenance 1 is a string.'];
        yield 'same maintenance twice' => [['maintenances' => [$maintenance, $maintenance]], 'Each maintenance id is given once.'];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('refused')]
    public function testItRefuses(array $data, string $message): void
    {
        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RecordsReader()->read($data);
    }

    public function testFiveHundredIncidentsAreRead(): void
    {
        $incidents = [];

        for ($i = 0; $i < 500; ++$i) {
            $incidents[] = ['id' => 'i'.$i, 'title' => 'I', 'impact' => 'degraded', 'components' => [], 'updates' => [['at' => '2026-11-12T10:00:00Z', 'state' => 'resolved', 'message' => 'Done.']]];
        }

        self::assertCount(500, new RecordsReader()->read(['incidents' => $incidents])->incidents);
    }

    public function testEveryMaintenanceIsRead(): void
    {
        $maintenance = static fn (string $id): array => ['id' => $id, 'title' => 'M', 'starts_at' => '2026-11-20T02:00:00Z', 'ends_at' => '2026-11-20T03:00:00Z'];

        $maintenances = new RecordsReader()->read(['maintenances' => [$maintenance('first'), $maintenance('second')]])->maintenances;

        self::assertSame(['first', 'second'], array_map(static fn (ScheduledMaintenance $maintenance): string => $maintenance->id, $maintenances));
    }
}
