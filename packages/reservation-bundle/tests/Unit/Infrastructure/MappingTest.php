<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\ORMSetup;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns the application's migrations create, as the mapping declares them on the entities.
 */
#[CoversClass(BookableResource::class)]
#[CoversClass(Reservation::class)]
final class MappingTest extends TestCase
{
    public function testTheResourceTable(): void
    {
        // The columns of an entity are declared on its constructor: building one reads them.
        self::assertSame('Salle', BookableResource::create('Salle')->name());

        self::assertSame([
            'table' => 'phil_reservation_resource',
            'id' => ['id'],
            'versioned' => false,
            'indexes' => [],
            'columns' => [
                'bufferBefore' => ['buffer_before', 'integer', null, false, false, null],
                'bufferAfter' => ['buffer_after', 'integer', null, false, false, null],
                'minimumNotice' => ['minimum_notice', 'integer', null, true, false, null],
                'maximumAdvance' => ['maximum_advance', 'integer', null, true, false, null],
                'minimumDuration' => ['minimum_duration', 'integer', null, true, false, null],
                'maximumDuration' => ['maximum_duration', 'integer', null, true, false, null],
                'slotStep' => ['slot_step', 'integer', null, true, false, null],
                'hours' => ['hours', 'json', null, true, false, null],
                'closedDays' => ['closed_days', 'json', null, false, false, null],
                'reminders' => ['reminders', 'json', null, false, false, null],
                'holdMinutes' => ['hold_minutes', 'integer', null, false, false, null],
                'active' => ['active', 'boolean', null, false, false, null],
                'claims' => ['claims', 'integer', null, false, false, null],
                'id' => ['id', 'string', 36, false, true, null],
                'name' => ['name', 'string', 200, false, false, null],
                'capacity' => ['capacity', 'integer', null, false, false, null],
                'timezone' => ['timezone', 'string', 64, false, false, null],
            ],
            'joins' => [],
        ], $this->describe(BookableResource::class));
    }

    public function testTheReservationTable(): void
    {
        $now = new DateTimeImmutable('2026-11-01 12:00 UTC');
        self::assertSame(2, Reservation::confirmed(BookableResource::create('Salle', 2), new Interval($now->modify('+1 day'), $now->modify('+25 hours')), 2, null, $now)->quantity());

        self::assertSame([
            'table' => 'phil_reservation',
            'id' => ['id'],
            'versioned' => true,
            'indexes' => [
                'phil_reservation_period' => ['resource', 'starts_at', 'ends_at'],
                'phil_reservation_state' => ['status', 'starts_at'],
            ],
            'columns' => [
                'holdExpiresAt' => ['hold_expires_at', 'datetime_utc_immutable', null, true, false, null],
                'lastReminder' => ['last_reminder', 'integer', null, true, false, null],
                'version' => ['version', 'integer', null, false, false, null],
                'id' => ['id', 'string', 36, false, true, null],
                'startsAt' => ['starts_at', 'datetime_utc_immutable', null, false, false, null],
                'endsAt' => ['ends_at', 'datetime_utc_immutable', null, false, false, null],
                'quantity' => ['quantity', 'integer', null, false, false, null],
                'holder' => ['holder', 'string', 255, true, false, null],
                'status' => ['status', 'string', 16, false, false, ReservationStatus::class],
                'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, false, null],
            ],
            'joins' => [
                'resource' => [['resource', false, 'CASCADE']],
            ],
        ], $this->describe(Reservation::class));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function describe(string $class): array
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $metadata = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config)->getClassMetadata($class);

        $columns = [];

        foreach ($metadata->fieldMappings as $name => $mapping) {
            $columns[$name] = [$mapping->columnName, $mapping->type, $mapping->length, true === $mapping->nullable, true === ($mapping->options['fixed'] ?? false), $mapping->enumType];
        }

        $joins = [];

        foreach ($metadata->associationMappings as $name => $association) {
            $joins[$name] = [];

            if (!$association instanceof ToOneOwningSideMapping) {
                continue;
            }

            foreach ($association->joinColumns as $column) {
                $joins[$name][] = [$column->name, $column->nullable, $column->onDelete];
            }
        }

        return [
            'table' => $metadata->getTableName(),
            'id' => $metadata->getIdentifierFieldNames(),
            'versioned' => $metadata->isVersioned,
            'indexes' => array_map(static fn (mixed $index): mixed => \is_array($index) ? $index['columns'] ?? null : null, $metadata->table['indexes'] ?? []),
            'columns' => $columns,
            'joins' => $joins,
        ];
    }
}
