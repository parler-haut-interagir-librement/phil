<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Move;

/**
 * One table of a tenant's rows, and how they are found: by the tenant column, or through the rows of a table that has
 * it — the table of a subclass of a joined hierarchy, the join table of a many-to-many association.
 *
 * The names are those of Doctrine's own SQL, quoted where the mapping quotes them; the tenant reference is always a
 * bound parameter.
 */
final readonly class CopyTable
{
    /**
     * @param string       $table    the table, as Doctrine names it in SQL
     * @param string       $where    the condition on the tenant's rows, with one `?` for the tenant reference
     * @param list<string> $orderBy  the columns that order the rows, so that both databases compare them in one order
     * @param ?string      $keyTable the table, unquoted, whose single key column may draw on a sequence
     * @param ?string      $key      that column, unquoted
     */
    public function __construct(
        public string $table,
        public string $where,
        public array $orderBy,
        public ?string $keyTable = null,
        public ?string $key = null,
    ) {
    }

    public function select(): string
    {
        return \sprintf('SELECT * FROM %s WHERE %s ORDER BY %s', $this->table, $this->where, implode(', ', $this->orderBy));
    }

    public function delete(): string
    {
        return \sprintf('DELETE FROM %s WHERE %s', $this->table, $this->where);
    }
}
