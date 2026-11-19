<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * Finds and replaces a user's name in the blame of every `Blameable` entity, `created_by` and `updated_by` alike: what
 * an access or erasure request needs, since the blame outlives the account. It reads and writes the tables directly:
 * soft-deleted rows and rows an SQL filter hides are found too, the listeners do not run — an erasure is no update of
 * the entities —, and nothing else of the rows changes.
 *
 * Given a `ManagerRegistry`, it reaches the tables of every entity manager, each table once per connection, and writes
 * the tables of each connection in one transaction of that connection. Once a connection's names are replaced, it
 * evicts the second-level cache regions of the classes it changed and detaches the managed entities that still hold
 * the old name, so that the next read gets the new one; whatever such an entity had pending is dropped with it.
 */
final readonly class BlameEraser
{
    private const array FIELDS = ['createdBy', 'updatedBy'];

    public function __construct(
        private ManagerRegistry|EntityManagerInterface $managers,
    ) {
    }

    /**
     * How many rows blame someone, by table; only the tables that have some.
     *
     * @return array<string, int>
     */
    public function find(string $who): array
    {
        $found = [];

        foreach ($this->groups() as $group) {
            foreach ($group['tables'] as $table => ['columns' => [$created, $updated]]) {
                $count = $group['connection']->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s = ? OR %s = ?', $table, $created, $updated), [$who, $who]);

                if (is_numeric($count) && $count > 0) {
                    $found[$table] = ($found[$table] ?? 0) + (int) $count;
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Replaces someone's name by another — a pseudonym that names nobody —, or by nobody.
     *
     * @return int how many names were replaced, two for a row that blames them twice
     */
    public function replace(string $who, ?string $replacement = null): int
    {
        $replaced = 0;

        foreach ($this->groups() as $group) {
            $replaced += $group['connection']->transactional(static function (Connection $connection) use ($group, $who, $replacement): int {
                $rows = 0;

                foreach ($group['tables'] as $table => ['columns' => $columns]) {
                    foreach ($columns as $column) {
                        $rows += (int) $connection->executeStatement(\sprintf('UPDATE %1$s SET %2$s = ? WHERE %2$s = ?', $table, $column), [$replacement, $who]);
                    }
                }

                return $rows;
            });

            Forget::after($group['managers'], Managers::classes($group), static fn (object $entity): bool => $entity instanceof Blameable && ($who === $entity->createdBy() || $who === $entity->updatedBy()));
        }

        return $replaced;
    }

    /**
     * The tables whose rows blame users, found or not: the stores an erasure report names.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return Managers::tableNames($this->groups());
    }

    /**
     * @return list<array{connection: Connection, managers: list<EntityManagerInterface>, tables: array<string, array{columns: list<string>, classes: list<array{EntityManagerInterface, class-string}>}>}>
     */
    private function groups(): array
    {
        return Managers::tables($this->managers, Blameable::class, self::FIELDS);
    }
}
