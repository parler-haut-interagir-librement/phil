<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Closure;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\PersonalFields;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Psr\Clock\ClockInterface;

/**
 * Takes the personal fields out of the frozen versions in `phil_record_version`: those of a person erased, those of
 * one record, or those of every version written before the personal fields were left out.
 *
 * The versions of a person are those of the records the person owns or is blamed for, and those the person froze, of
 * the classes `PersonalFields` gives fields: run it before `BlameEraser` and `OwnerEraser` replace the person's name,
 * which is how it finds them. A redacted version loses the keys of the personal fields of its record's classes,
 * sealed or not, and is dated in `redacted_at`; `Versions` then refuses to go back to it, or to an older one.
 *
 * It reads and writes the table directly, in one transaction per connection, the table once per connection when a
 * `ManagerRegistry` gives several entity managers. Once a connection is written, the second-level cache region of
 * the versions is evicted and the versions it changed are detached, wherever they are managed.
 *
 * @phpstan-type Store array{connection: Connection, managers: list<EntityManagerInterface>, table: string, columns: array<string, string>, personal: array<string, list<string>>, links: array<string, list<array{string, int}>>}
 * @phpstan-type Row array{table: string, state: array<string, mixed>}
 */
final readonly class VersionEraser
{
    private const int BATCH = 500;

    public function __construct(
        private ManagerRegistry|EntityManagerInterface $managers,
        private PersonalFields $personalFields,
        private ClockInterface $clock,
    ) {
    }

    /**
     * How many versions of a person still hold personal fields.
     */
    public function find(string $who): int
    {
        $found = 0;

        foreach ($this->stores() as $store) {
            $found += \count($this->versionsOf($store, $who));
        }

        return $found;
    }

    /**
     * Redacts the versions of a person.
     *
     * @return int how many versions were redacted now
     */
    public function redact(string $who): int
    {
        $redacted = 0;

        foreach ($this->stores() as $store) {
            $redacted += $this->write($store, fn (): array => $this->versionsOf($store, $who), true);
        }

        return $redacted;
    }

    /**
     * Redacts every version of one record, whatever classes `PersonalFields` gives fields: an erasure of the record
     * itself. Going back to any of them is refused from then on.
     *
     * @return int how many versions were redacted now
     *
     * @throws LogicException when no entity manager of the registry manages the record's class
     */
    public function redactRecord(Versionable $entity): int
    {
        $manager = $this->managerOf($entity);
        $table = $manager->getClassMetadata($manager->getClassMetadata($entity::class)->rootEntityName)->getTableName();

        foreach ($this->stores() as $store) {
            if ($store['connection'] === $manager->getConnection()) {
                $store['personal'][$table] ??= [];

                return $this->write($store, fn (): array => $this->rows($store, \sprintf('%s = ? AND %s = ? AND %s IS NULL', $store['columns']['subjectTable'], $store['columns']['subjectId'], $store['columns']['redactedAt']), [$table, $entity->versionSubjectId()]), true);
            }
        }

        return 0;
    }

    /**
     * Takes the personal fields out of every version that still holds them in plain, as `PersonalFieldPolicy::Exclude`
     * leaves them out; a sealed value stays, and no version is marked redacted. What an upgrade runs once over the
     * versions written before.
     *
     * @return int how many versions held some, and were changed unless it is a dry run
     */
    public function stripPersonalFields(bool $dryRun = false): int
    {
        $changed = 0;

        foreach ($this->stores() as $store) {
            if ([] === $store['personal']) {
                continue;
            }

            $last = 0;

            do {
                $rows = $this->rows($store, \sprintf('%s IN (?) AND %s > ? ORDER BY %2$s LIMIT %d', $store['columns']['subjectTable'], $store['columns']['id'], self::BATCH), [array_keys($store['personal']), $last], [ArrayParameterType::STRING, ParameterType::INTEGER]);
                $last = array_key_last($rows) ?? $last;
                $plain = array_filter($rows, static fn (array $row): bool => [] !== self::plainKeys($row['state'], $store['personal'][$row['table']] ?? []));
                $changed += $dryRun ? \count($plain) : $this->write($store, static fn (): array => $plain, false);
            } while (self::BATCH === \count($rows));
        }

        return $changed;
    }

    /**
     * The tables that hold versions, found or not: the stores an erasure report names.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = array_values(array_unique(array_map(static fn (array $store): string => $store['table'], $this->stores())));
        sort($tables);

        return $tables;
    }

    /**
     * The versions of a person in one store, by identifier.
     *
     * @param Store $store
     *
     * @return array<int, Row>
     */
    private function versionsOf(array $store, string $who): array
    {
        if ([] === $store['personal']) {
            return [];
        }

        $columns = $store['columns'];
        $versions = $this->rows($store, \sprintf('%s = ? AND %s IN (?) AND %s IS NULL', $columns['createdBy'], $columns['subjectTable'], $columns['redactedAt']), [$who, array_keys($store['personal'])], [ParameterType::STRING, ArrayParameterType::STRING]);

        foreach ($store['links'] as $table => $links) {
            foreach ($links as [$sql, $placeholders]) {
                $ids = array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : '', $store['connection']->fetchFirstColumn($sql, array_fill(0, $placeholders, $who)));

                foreach (array_chunk($ids, self::BATCH) as $chunk) {
                    $versions += $this->rows($store, \sprintf('%s = ? AND %s IN (?) AND %s IS NULL', $columns['subjectTable'], $columns['subjectId'], $columns['redactedAt']), [$table, $chunk], [ParameterType::STRING, ArrayParameterType::STRING]);
                }
            }
        }

        return $versions;
    }

    /**
     * Rewrites the versions the query gives, in one transaction, then forgets them in every manager on the connection.
     *
     * @param Store                      $store
     * @param Closure(): array<int, Row> $versions
     */
    private function write(array $store, Closure $versions, bool $redact): int
    {
        $columns = $store['columns'];
        $now = $this->clock->now();

        $ids = $store['connection']->transactional(static function (Connection $connection) use ($store, $columns, $versions, $redact, $now): array {
            $ids = [];

            foreach ($versions() as $id => ['table' => $table, 'state' => $state]) {
                $fields = $store['personal'][$table] ?? [];
                $state = $redact ? array_diff_key($state, array_flip($fields)) : array_diff_key($state, array_flip(self::plainKeys($state, $fields)));
                $set = $redact ? \sprintf('%s = ?, %s = ?', $columns['state'], $columns['redactedAt']) : \sprintf('%s = ?', $columns['state']);
                $values = $redact ? [$state, $now, $id] : [$state, $id];
                $types = $redact ? [Types::JSON, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER] : [Types::JSON, ParameterType::INTEGER];
                $connection->executeStatement(\sprintf('UPDATE %s SET %s WHERE %s = ?', $store['table'], $set, $columns['id']), $values, $types);
                $ids[$id] = true;
            }

            return $ids;
        });

        if ([] !== $ids) {
            Forget::after($store['managers'], array_map(static fn (EntityManagerInterface $manager): array => [$manager, RecordVersion::class], $store['managers']), static fn (object $entity): bool => $entity instanceof RecordVersion && isset($ids[(int) $entity->id()]));
        }

        return \count($ids);
    }

    /**
     * @param Store                                  $store
     * @param list<mixed>                            $values
     * @param list<ArrayParameterType|ParameterType> $types
     *
     * @return array<int, Row>
     */
    private function rows(array $store, string $where, array $values, array $types = []): array
    {
        $columns = $store['columns'];
        $json = Type::getType(Types::JSON);
        $platform = $store['connection']->getDatabasePlatform();
        $rows = [];

        foreach ($store['connection']->fetchAllNumeric(\sprintf('SELECT %s, %s, %s FROM %s WHERE %s', $columns['id'], $columns['subjectTable'], $columns['state'], $store['table'], $where), $values, $types) as [$id, $table, $state]) {
            $decoded = $json->convertToPHPValue($state, $platform);
            \assert(is_numeric($id) && \is_string($table) && \is_array($decoded));

            /** @var array<string, mixed> $decoded a state is an object keyed by field names */
            $rows[(int) $id] = ['table' => $table, 'state' => $decoded];
        }

        return $rows;
    }

    /**
     * The personal fields a state holds in plain, not sealed.
     *
     * @param array<string, mixed> $state
     * @param list<string>         $fields
     *
     * @return list<string>
     */
    private static function plainKeys(array $state, array $fields): array
    {
        return array_values(array_filter($fields, static fn (string $field): bool => \array_key_exists($field, $state) && (!\is_array($state[$field]) || !\array_key_exists(Versions::SEALED, $state[$field]))));
    }

    private function managerOf(Versionable $entity): EntityManagerInterface
    {
        if ($this->managers instanceof EntityManagerInterface) {
            return $this->managers;
        }

        $manager = $this->managers->getManagerForClass($entity::class);

        return $manager instanceof EntityManagerInterface ? $manager : throw new LogicException(\sprintf('No entity manager manages %s.', $entity::class));
    }

    /**
     * Where versions are kept, once per connection: the table, its columns, the personal fields by record table, and
     * the queries that find the identifiers of the records a person owns or is blamed for, by record table.
     *
     * @return list<Store>
     */
    private function stores(): array
    {
        $stores = [];

        foreach (Managers::all($this->managers) as $manager) {
            $all = $manager->getMetadataFactory()->getAllMetadata();
            $version = array_find($all, static fn (ClassMetadata $metadata): bool => RecordVersion::class === $metadata->getName());

            if (!$version instanceof ClassMetadata) {
                continue;
            }

            $connection = $manager->getConnection();
            $platform = $connection->getDatabasePlatform();
            $quotes = $manager->getConfiguration()->getQuoteStrategy();
            $table = $quotes->getTableName($version, $platform);
            $key = spl_object_id($connection).'|'.$table;

            if (isset($stores[$key])) {
                $stores[$key]['managers'][] = $manager;

                continue;
            }

            $columns = [];

            foreach (['id', 'subjectTable', 'subjectId', 'state', 'redactedAt', 'createdBy'] as $field) {
                $columns[$field] = $quotes->getColumnName($field, $version, $platform);
            }

            $stores[$key] = ['connection' => $connection, 'managers' => [$manager], 'table' => $table, 'columns' => $columns, ...$this->records($manager, $all)];
        }

        return array_values($stores);
    }

    /**
     * The personal fields of the versioned records, by root table, and the queries that link them to a person: the
     * owner, then the blame, each where the table maps it, with the number of times it names the person.
     *
     * @param list<ClassMetadata<object>> $all
     *
     * @return array{personal: array<string, list<string>>, links: array<string, list<array{string, int}>>}
     */
    private function records(EntityManagerInterface $manager, array $all): array
    {
        $personal = [];
        $links = [];
        $platform = $manager->getConnection()->getDatabasePlatform();
        $quotes = $manager->getConfiguration()->getQuoteStrategy();

        foreach ($all as $metadata) {
            $fields = $this->personalFields->of($metadata->getName());

            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || !is_a($metadata->getName(), Versionable::class, true) || [] === $fields) {
                continue;
            }

            $root = $manager->getClassMetadata($metadata->rootEntityName);
            $table = $root->getTableName();
            $personal[$table] = array_values(array_unique([...$personal[$table] ?? [], ...$fields]));

            if ($root->isIdentifierComposite) {
                continue;
            }

            $from = \sprintf('SELECT %s FROM %s WHERE ', $quotes->getColumnName($root->getSingleIdentifierFieldName(), $root, $platform), $quotes->getTableName($root, $platform));

            if (is_a($metadata->getName(), Ownable::class, true) && $metadata->hasField(Ownable::OWNER_FIELD)) {
                $sql = $from.$quotes->getColumnName(Ownable::OWNER_FIELD, $metadata, $platform).' = ?';
                $links[$table][$sql] = [$sql, 1];
            }

            if (is_a($metadata->getName(), Blameable::class, true) && $metadata->hasField('createdBy')) {
                $sql = $from.\sprintf('%s = ? OR %s = ?', $quotes->getColumnName('createdBy', $metadata, $platform), $quotes->getColumnName('updatedBy', $metadata, $platform));
                $links[$table][$sql] = [$sql, 2];
            }
        }

        ksort($personal);

        return ['personal' => $personal, 'links' => array_map(array_values(...), $links)];
    }
}
