<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\Ownable;

/**
 * Finds and replaces an owner in every `Ownable` entity: what an access or erasure request needs, since the column
 * outlives the account. It reads and writes the tables directly: soft-deleted and deactivated rows and rows an SQL
 * filter hides are found too, the listeners do not run, and nothing else of the rows changes. Entities already loaded
 * keep the old owner until they are read again.
 */
final readonly class OwnerEraser
{
    public function __construct(
        private EntityManagerInterface $manager,
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

        foreach ($this->columns() as $table => [$column]) {
            $count = $this->manager->getConnection()->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $table, $column), [$owner->value]);

            if (is_numeric($count) && $count > 0) {
                $found[$table] = (int) $count;
            }
        }

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

        foreach ($this->columns() as $table => [$column]) {
            $replaced += (int) $this->manager->getConnection()->executeStatement(\sprintf('UPDATE %1$s SET %2$s = ? WHERE %2$s = ?', $table, $column), [$replacement?->value, $owner->value]);
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
        return array_keys($this->columns());
    }

    /**
     * @return array<string, list<string>>
     */
    private function columns(): array
    {
        return MappedColumns::of($this->manager, Ownable::class, [Ownable::OWNER_FIELD]);
    }
}
