<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Phil\DoctrineBehaviours\Contract\Ownable;

/**
 * Finds and replaces an owner in every `Ownable` entity: what an access or erasure request needs, since the column
 * outlives the account. It reads and writes the tables directly: soft-deleted and deactivated rows and rows an SQL
 * filter hides are found too, the listeners do not run, and nothing else of the rows changes.
 *
 * Given a `ManagerRegistry`, it reaches the tables of every entity manager, each table once per connection, and writes
 * the tables of each connection in one transaction of that connection. Once a connection's owners are replaced, it
 * evicts the second-level cache regions of the classes it changed and detaches the managed entities that still hold
 * the old owner, so that the next read gets the new one; whatever such an entity had pending is dropped with it.
 */
final readonly class OwnerEraser
{
    public function __construct(
        private ManagerRegistry|EntityManagerInterface $managers,
    ) {
    }

    /**
     * How many rows someone owns, by table; only the tables that have some.
     *
     * @return array<string, int>
     */
    public function find(OwnerId $owner): array
    {
        $found = [];

        foreach ($this->groups() as $group) {
            foreach ($group['tables'] as $table => ['columns' => [$column]]) {
                $count = $group['connection']->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $table, $column), [$owner->value]);

                if (is_numeric($count) && $count > 0) {
                    $found[$table] = ($found[$table] ?? 0) + (int) $count;
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Replaces an owner by another — a pseudonym that names nobody —, or by nobody.
     *
     * @return int how many rows changed hands
     */
    public function replace(OwnerId $owner, ?OwnerId $replacement = null): int
    {
        $replaced = 0;

        foreach ($this->groups() as $group) {
            $replaced += $group['connection']->transactional(static function (Connection $connection) use ($group, $owner, $replacement): int {
                $rows = 0;

                foreach ($group['tables'] as $table => ['columns' => [$column]]) {
                    $rows += (int) $connection->executeStatement(\sprintf('UPDATE %1$s SET %2$s = ? WHERE %2$s = ?', $table, $column), [$replacement?->value, $owner->value]);
                }

                return $rows;
            });

            Forget::after($group['managers'], Managers::classes($group), static fn (object $entity): bool => $entity instanceof Ownable && $owner->value === $entity->ownerId()?->value);
        }

        return $replaced;
    }

    /**
     * The tables whose rows hold owners, found or not: the stores an erasure report names.
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
        return Managers::tables($this->managers, Ownable::class, [Ownable::OWNER_FIELD]);
    }
}
