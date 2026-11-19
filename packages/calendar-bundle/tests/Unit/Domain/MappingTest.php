<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Model\EventTranslation;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Model\VenueTranslation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the calendar.
 */
#[CoversClass(Event::class)]
#[CoversClass(EventHost::class)]
#[CoversClass(Venue::class)]
#[CoversClass(EventTranslation::class)]
#[CoversClass(VenueTranslation::class)]
#[CoversClass(Attendance::class)]
final class MappingTest extends TestCase
{
    public function testTheEventTable(): void
    {
        self::assertSame(['phil_calendar_event', ['id'], [
            'description' => ['description', 'text', null, true, null],
            'location' => ['location', 'string', 255, true, null],
            'url' => ['url', 'string', 2048, true, null],
            'image' => ['image', 'string', 2048, true, null],
            'rule' => ['rule', 'phil_rrule', null, true, null],
            'exceptions' => ['exceptions', 'json', null, false, null],
            'addedDates' => ['added_dates', 'json', null, true, null],
            'tags' => ['tags', 'json', null, false, null],
            'cancelled' => ['cancelled', 'boolean', null, false, null],
            'dateLinks' => ['date_links', 'json', null, false, null],
            'capacity' => ['capacity', 'integer', null, true, null],
            'proposalState' => ['proposal_state', 'string', 16, false, null],
            'proposedBy' => ['proposed_by', 'string', 129, true, null],
            'seriesEndsAt' => ['series_ends_at', 'datetime_utc_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'title' => ['title', 'string', 200, false, null],
            'startsAt' => ['starts_at', 'datetime_utc_immutable', null, false, null],
            'endsAt' => ['ends_at', 'datetime_utc_immutable', null, false, null],
            'allDay' => ['all_day', 'boolean', null, false, null],
            'timezone' => ['timezone', 'string', 64, false, null],
        ]], $this->table(Event::class));
        self::assertSame([['columns' => ['starts_at', 'series_ends_at']]], array_values($this->metadata(Event::class)->table['indexes'] ?? []));
        self::assertTrue($this->metadata(Event::class)->isVersioned);

        $event = Event::schedule('Soirée', new DateTimeImmutable('2026-11-10 19:00'), new DateTimeImmutable('2026-11-10 22:00'));
        self::assertSame($this->metadata(Event::class)->fieldMappings['id']->length, \strlen($event->id));
    }

    public function testTheHostTable(): void
    {
        self::assertSame(['phil_calendar_event_host', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'kind' => ['kind', 'string', 64, false, null],
            'reference' => ['reference', 'string', 64, false, null],
        ]], $this->table(EventHost::class));
        self::assertSame([['columns' => ['kind', 'reference']]], array_values($this->metadata(EventHost::class)->table['indexes'] ?? []));

        $host = new EventHost(Event::schedule('Soirée', new DateTimeImmutable('2026-11-10 19:00'), new DateTimeImmutable('2026-11-10 22:00')), str_repeat('k', 64), str_repeat('r', 64));
        self::assertSame([$this->metadata(EventHost::class)->fieldMappings['kind']->length, $this->metadata(EventHost::class)->fieldMappings['reference']->length], [\strlen($host->kind), \strlen($host->reference)]);

        $event = $this->metadata(EventHost::class)->associationMappings['event'] ?? null;
        self::assertNotNull($event);
        self::assertSame([Event::class, 'event', 'id', false, 'CASCADE'], [$event->targetEntity, $event->joinColumns[0]->name ?? null, $event->joinColumns[0]->referencedColumnName ?? null, $event->joinColumns[0]->nullable ?? null, $event->joinColumns[0]->onDelete ?? null]);
    }

    public function testTheVenueTable(): void
    {
        self::assertSame(['phil_calendar_venue', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'name' => ['name', 'string', 200, false, null],
            'room' => ['room', 'string', 100, true, null],
            'address' => ['address', 'string', 500, true, null],
            'latitude' => ['latitude', 'float', null, true, null],
            'longitude' => ['longitude', 'float', null, true, null],
            'listing' => ['listing', 'string', 129, true, null],
        ]], $this->table(Venue::class));
        self::assertSame([['columns' => ['listing']]], array_values($this->metadata(Venue::class)->table['indexes'] ?? []));

        $venue = $this->metadata(Event::class)->associationMappings['venue'] ?? null;
        self::assertNotNull($venue);
        self::assertSame([Venue::class, 'venue', 'id', true, 'SET NULL'], [$venue->targetEntity, $venue->joinColumns[0]->name ?? null, $venue->joinColumns[0]->referencedColumnName ?? null, $venue->joinColumns[0]->nullable ?? null, $venue->joinColumns[0]->onDelete ?? null]);
        self::assertSame(36, \strlen(new Venue('Salle')->id));
    }

    /**
     * The translations of events and venues, one row per record and locale; attendances, one per event, occurrence and
     * member.
     */
    public function testTheTranslationAndAttendanceTables(): void
    {
        self::assertSame(['phil_calendar_event_translation', ['id'], [
            'locale' => ['locale', 'string', 12, false, null],
            'id' => ['id', 'integer', null, false, null],
            'title' => ['title', 'string', 200, false, null],
            'description' => ['description', 'text', null, true, null],
        ]], $this->table(EventTranslation::class));
        self::assertSame(['phil_calendar_venue_translation', ['id'], [
            'locale' => ['locale', 'string', 12, false, null],
            'id' => ['id', 'integer', null, false, null],
            'name' => ['name', 'string', 200, false, null],
        ]], $this->table(VenueTranslation::class));
        self::assertSame(['phil_calendar_attendance', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'member' => ['member', 'string', 129, false, null],
            'occurrence' => ['occurrence', 'string', 19, false, null],
            'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(Attendance::class));
        self::assertSame(['columns' => ['event', 'locale']], $this->metadata(EventTranslation::class)->table['uniqueConstraints']['phil_calendar_event_translation_translatable_locale'] ?? null);
        self::assertSame(['columns' => ['venue', 'locale']], $this->metadata(VenueTranslation::class)->table['uniqueConstraints']['phil_calendar_venue_translation_translatable_locale'] ?? null);
        self::assertSame(['columns' => ['event', 'occurrence', 'member']], $this->metadata(Attendance::class)->table['uniqueConstraints']['phil_calendar_attendance_unique'] ?? null);
        self::assertSame(['columns' => ['member']], $this->metadata(Attendance::class)->table['indexes']['phil_calendar_attendance_member'] ?? null);

        foreach ([[EventTranslation::class, Event::class, 'event'], [VenueTranslation::class, Venue::class, 'venue'], [Attendance::class, Event::class, 'event']] as [$class, $target, $column]) {
            $field = EventTranslation::class === $class || VenueTranslation::class === $class ? 'translatable' : 'event';
            $association = $this->metadata($class)->associationMappings[$field] ?? null;
            self::assertNotNull($association);
            self::assertSame([$target, $column, 'id', false, 'CASCADE'], [$association->targetEntity, $association->joinColumns[0]->name ?? null, $association->joinColumns[0]->referencedColumnName ?? null, $association->joinColumns[0]->nullable ?? null, $association->joinColumns[0]->onDelete ?? null]);
        }
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<int, string>, array<string, array{string, string, ?int, ?bool, mixed}>}
     */
    private function table(string $class): array
    {
        $metadata = $this->metadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
