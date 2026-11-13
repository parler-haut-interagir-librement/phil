<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Application;

use Phil\StatusPageBundle\Application\StatusPageBuilder;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Infrastructure\Source\DeclaredStatuses;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use Phil\StatusPageBundle\Tests\Unit\Support\MemoryRecords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StatusPageBuilder::class)]
final class StatusPageBuilderTest extends TestCase
{
    public function testComponentsAreOperationalWithoutAnythingToSay(): void
    {
        $page = $this->builder(new MemoryRecords())->build();

        self::assertSame('Example status', $page->title);
        self::assertEquals(Fixtures::now(), $page->generatedAt);
        self::assertSame(['website', 'api', 'sign-in'], array_map(static fn (ComponentState $state): string => $state->component->id, $page->components));
        self::assertSame([ComponentStatus::Operational, ComponentStatus::Operational, ComponentStatus::Operational], $this->statuses($page->components));
        self::assertSame([], $page->activeIncidents);
        self::assertSame([], $page->history);
        self::assertSame([], $page->maintenancesInProgress);
        self::assertSame([], $page->upcomingMaintenances);
    }

    public function testTheSourceMaintenancesAndIncidentsMakeTheStateOfAComponent(): void
    {
        $records = new MemoryRecords(
            [
                Fixtures::incident('api-slow', ComponentStatus::Degraded, ['api'], '-1 hour'),
                Fixtures::incident('api-down', ComponentStatus::MajorOutage, ['api'], '-30 minutes'),
                Fixtures::incident('maintenance-hiccup', ComponentStatus::Degraded, ['sign-in'], '-10 minutes'),
                Fixtures::incident('over', ComponentStatus::MajorOutage, ['website'], '-2 hours', '-1 hour'),
                Fixtures::incident('future', ComponentStatus::MajorOutage, ['website'], '+1 hour'),
            ],
            [
                Fixtures::maintenance('website-work', ['website'], '-1 hour', '+1 hour'),
                Fixtures::maintenance('sign-in-work', ['sign-in'], '-1 hour', '+1 hour'),
            ],
        );

        $page = $this->builder($records, ['website' => ComponentStatus::MajorOutage, 'sign-in' => ComponentStatus::Operational])->build();

        // The maintenance of the website hides its declared outage; the incidents of the API make it the worst of
        // their impacts; an incident during a maintenance shows over it.
        self::assertSame([ComponentStatus::Maintenance, ComponentStatus::MajorOutage, ComponentStatus::Degraded], $this->statuses($page->components));
    }

    public function testIncidentsGoingOnAndInTheHistoryAreNewestFirst(): void
    {
        $records = new MemoryRecords([
            Fixtures::incident('older', ComponentStatus::Degraded, [], '-3 hours'),
            Fixtures::incident('newer', ComponentStatus::Degraded, [], '-1 hour'),
            Fixtures::incident('resolved-old', ComponentStatus::Degraded, [], '-20 days', '-19 days'),
            Fixtures::incident('resolved-new', ComponentStatus::Degraded, [], '-2 days', '-1 day'),
            Fixtures::incident('resolved-at-the-limit', ComponentStatus::Degraded, [], '-31 days', '-30 days'),
            Fixtures::incident('resolved-too-long-ago', ComponentStatus::Degraded, [], '-32 days', '-30 days -1 second'),
        ]);

        $page = $this->builder($records, [], 30)->build();

        self::assertSame(['newer', 'older'], array_map(static fn (Incident $incident): string => $incident->id, $page->activeIncidents));
        self::assertSame(['resolved-new', 'resolved-old', 'resolved-at-the-limit'], array_map(static fn (Incident $incident): string => $incident->id, $page->history));
    }

    public function testAnIncidentResolvedInTheFutureIsNotInTheHistory(): void
    {
        $page = $this->builder(new MemoryRecords([Fixtures::incident('later', ComponentStatus::Degraded, [], '+1 hour', '+2 hours')]))->build();

        self::assertSame([], $page->history);
        self::assertSame([], $page->activeIncidents);
    }

    public function testMaintenancesInProgressAndUpcomingAreSoonestFirst(): void
    {
        $records = new MemoryRecords([], [
            Fixtures::maintenance('later', [], '+2 days', '+3 days'),
            Fixtures::maintenance('sooner', [], '+1 day', '+2 days'),
            Fixtures::maintenance('started-later', [], '-1 hour', '+1 hour'),
            Fixtures::maintenance('started-sooner', [], '-2 hours', '+1 hour'),
            Fixtures::maintenance('over', [], '-2 hours', '-1 hour'),
        ]);

        $page = $this->builder($records)->build();

        self::assertSame(['started-sooner', 'started-later'], array_map(static fn (ScheduledMaintenance $maintenance): string => $maintenance->id, $page->maintenancesInProgress));
        self::assertSame(['sooner', 'later'], array_map(static fn (ScheduledMaintenance $maintenance): string => $maintenance->id, $page->upcomingMaintenances));
    }

    /**
     * @param array<string, ComponentStatus> $declared
     */
    private function builder(MemoryRecords $records, array $declared = [], int $historyDays = 90): StatusPageBuilder
    {
        return new StatusPageBuilder('Example status', Fixtures::components(), new DeclaredStatuses($declared), $records, new MockClock(Fixtures::now()), $historyDays);
    }

    /**
     * @param list<ComponentState> $states
     *
     * @return list<ComponentStatus>
     */
    private function statuses(array $states): array
    {
        return array_map(static fn (ComponentState $state): ComponentStatus => $state->status, $states);
    }

    public function testTheHistoryCoversNinetyDaysByDefault(): void
    {
        $records = new MemoryRecords([
            Fixtures::incident('inside', ComponentStatus::Degraded, [], '-91 days', '-89 days -12 hours'),
            Fixtures::incident('at-the-limit', ComponentStatus::Degraded, [], '-91 days', '-90 days'),
            Fixtures::incident('outside', ComponentStatus::Degraded, [], '-92 days', '-90 days -1 second'),
        ]);

        $page = new StatusPageBuilder('Example status', Fixtures::components(), new DeclaredStatuses([]), $records, new MockClock(Fixtures::now()))->build();

        self::assertSame(['inside', 'at-the-limit'], array_map(static fn (Incident $incident): string => $incident->id, $page->history));
    }

    public function testAnIncidentStartedNowAndResolvedIsInTheHistory(): void
    {
        $page = $this->builder(new MemoryRecords([Fixtures::incident('instant', ComponentStatus::Degraded, [], '+0 seconds', '+1 second')]))->build();

        self::assertSame(['instant'], array_map(static fn (Incident $incident): string => $incident->id, $page->history));
    }

    public function testADeclaredStateIsKeptWhenNothingElseSaysOtherwise(): void
    {
        $page = $this->builder(new MemoryRecords(), ['api' => ComponentStatus::Degraded])->build();

        self::assertSame([ComponentStatus::Operational, ComponentStatus::Degraded, ComponentStatus::Operational], $this->statuses($page->components));
    }

    public function testAMaintenanceOnlyChangesTheComponentsItAffects(): void
    {
        $page = $this->builder(new MemoryRecords([], [Fixtures::maintenance('api-work', ['api'], '-1 hour', '+1 hour')]))->build();

        self::assertSame([ComponentStatus::Operational, ComponentStatus::Maintenance, ComponentStatus::Operational], $this->statuses($page->components));
    }
}
