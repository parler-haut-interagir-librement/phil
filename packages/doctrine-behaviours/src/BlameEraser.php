<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * Finds and replaces a user's name in the blame of every `Blameable` entity, `created_by` and `updated_by` alike: what
 * an access or erasure request needs, since the blame outlives the account. It reads and writes the tables directly:
 * soft-deleted rows and rows an SQL filter hides are found too, the listeners do not run — an erasure is no update of
 * the entities —, and nothing else of the rows changes. Entities already loaded keep the old name until they are read
 * again.
 */
final readonly class BlameEraser
{
    public function __construct(
        private EntityManagerInterface $manager,
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

        foreach ($this->columns() as $table => [$created, $updated]) {
            $count = $this->manager->getConnection()->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s = ? OR %s = ?', $table, $created, $updated), [$who, $who]);

            if (is_numeric($count) && $count > 0) {
                $found[$table] = (int) $count;
            }
        }

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

        foreach ($this->columns() as $table => $columns) {
            foreach ($columns as $column) {
                $replaced += (int) $this->manager->getConnection()->executeStatement(\sprintf('UPDATE %1$s SET %2$s = ? WHERE %2$s = ?', $table, $column), [$replacement, $who]);
            }
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
        return array_keys($this->columns());
    }

    /**
     * @return array<string, list<string>>
     */
    private function columns(): array
    {
        return MappedColumns::of($this->manager, Blameable::class, ['createdBy', 'updatedBy']);
    }
}
