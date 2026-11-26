<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Catalogue\OfferedCourse;
use Phil\CoursesBundle\Domain\Contract\CatalogueRepository;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * The catalogues and their entries through DBAL — the tags of an entry kept as a JSON list, its price as minor units
 * and a currency —; the courses a catalogue offers are read with their entries and groups of equivalent courses in one
 * query.
 */
final readonly class DbalCatalogueRepository implements CatalogueRepository
{
    /** How many catalogues `shownTo()` reads at most. */
    public const int MAX_SHOWN = 200;

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(Catalogue $catalogue): void
    {
        $this->rows->put(CourseTables::CATALOGUE, ['id' => $catalogue->id->value], [
            'title' => $catalogue->title,
            'description' => $catalogue->description,
            'audience' => $catalogue->audience->value,
            'unit' => $catalogue->unit,
            'created_at' => $catalogue->createdAt,
        ], ['created_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(CatalogueId $id): ?Catalogue
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::CATALOGUE.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->catalogue($row);
    }

    #[Override]
    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_map($this->catalogue(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::CATALOGUE.' ORDER BY title, id LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
        ));
    }

    #[Override]
    public function shownTo(array $units): array
    {
        $public = 'SELECT * FROM '.CourseTables::CATALOGUE.' WHERE audience = ?';
        $order = ' ORDER BY title, id LIMIT '.self::MAX_SHOWN;

        if ([] === $units) {
            return array_map($this->catalogue(...), $this->connection->fetchAllAssociative($public.$order, [CatalogueAudience::Public->value]));
        }

        return array_map($this->catalogue(...), $this->connection->fetchAllAssociative(
            $public.' OR (audience = ? AND unit IN (?))'.$order,
            [CatalogueAudience::Public->value, CatalogueAudience::Unit->value, $units],
            [2 => ArrayParameterType::STRING],
        ));
    }

    #[Override]
    public function remove(CatalogueId $id): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($id): void {
            $connection->delete(CourseTables::CATALOGUE_ENTRY, ['catalogue_id' => $id->value]);
            $connection->delete(CourseTables::CATALOGUE, ['id' => $id->value]);
        });
    }

    #[Override]
    public function saveEntries(CatalogueId $catalogue, array $entries): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($catalogue, $entries): void {
            $connection->delete(CourseTables::CATALOGUE_ENTRY, ['catalogue_id' => $catalogue->value]);

            foreach ($entries as $entry) {
                $connection->insert(CourseTables::CATALOGUE_ENTRY, [
                    'catalogue_id' => $catalogue->value,
                    'course_id' => $entry->course->value,
                    'position' => $entry->position,
                    'self_enrollment' => $entry->selfEnrollment,
                    'price_minor' => $entry->price?->minorUnits,
                    'price_currency' => $entry->price?->currency,
                    'tags' => json_encode($entry->tags, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                ], ['self_enrollment' => Types::BOOLEAN, 'price_minor' => Types::BIGINT]);
            }
        });
    }

    #[Override]
    public function entries(CatalogueId $catalogue): array
    {
        return array_map(fn (array $row): CatalogueEntry => $this->entryOf($catalogue, $row), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::CATALOGUE_ENTRY.' WHERE catalogue_id = ? ORDER BY position, course_id',
            [$catalogue->value],
        ));
    }

    #[Override]
    public function entry(CatalogueId $catalogue, CourseId $course): ?CatalogueEntry
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::CATALOGUE_ENTRY.' WHERE catalogue_id = ? AND course_id = ?', [$catalogue->value, $course->value]);

        return false === $row ? null : $this->entryOf($catalogue, $row);
    }

    #[Override]
    public function offered(CatalogueId $catalogue): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT e.course_id, e.position, e.self_enrollment, e.price_minor, e.price_currency, e.tags,'
            .' c.title, c.description, c.origin, c.source, c.ordered, c.created_at, q.group_id, q.language'
            .' FROM '.CourseTables::CATALOGUE_ENTRY.' e'
            .' INNER JOIN '.CourseTables::COURSE.' c ON c.id = e.course_id'
            .' LEFT JOIN '.CourseTables::EQUIVALENT.' q ON q.course_id = e.course_id'
            .' WHERE e.catalogue_id = ? ORDER BY e.position, e.course_id',
            [$catalogue->value],
        );

        return array_map(fn (array $row): OfferedCourse => new OfferedCourse(
            $this->entryOf($catalogue, $row),
            new Course(
                CourseId::of(Rows::text($row['course_id'])),
                Rows::text($row['title']),
                $this->rows->requiredMoment($row['created_at']),
                Rows::text($row['description']),
                CourseOrigin::tryFrom(Rows::text($row['origin'])) ?? CourseOrigin::Native,
                Rows::nullableText($row['source']),
                Rows::bool($row['ordered']),
            ),
            null === $row['group_id'] ? null : EquivalenceGroupId::of(Rows::text($row['group_id'])),
            Rows::nullableText($row['language']),
        ), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function catalogue(array $row): Catalogue
    {
        $audience = CatalogueAudience::tryFrom(Rows::text($row['audience'])) ?? CatalogueAudience::Unit;

        return new Catalogue(
            CatalogueId::of(Rows::text($row['id'])),
            Rows::text($row['title']),
            $this->rows->requiredMoment($row['created_at']),
            Rows::text($row['description']),
            $audience,
            Rows::nullableText($row['unit']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entryOf(CatalogueId $catalogue, array $row): CatalogueEntry
    {
        $minor = Rows::nullableInt($row['price_minor']);
        $currency = Rows::nullableText($row['price_currency']);

        return new CatalogueEntry(
            $catalogue,
            CourseId::of(Rows::text($row['course_id'])),
            max(0, Rows::int($row['position'])),
            Rows::bool($row['self_enrollment']),
            null === $minor || null === $currency ? null : CoursePrice::of($minor, $currency),
            $this->tags($row['tags']),
        );
    }

    /**
     * The tags kept as a JSON list; what is not a list of texts reads as none.
     *
     * @return list<string>
     */
    private function tags(mixed $json): array
    {
        try {
            $values = json_decode(Rows::text($json), true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
