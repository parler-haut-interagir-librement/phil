<?php

declare(strict_types=1);

namespace Phil\Enums\Sort;

use Phil\Enums\Attribute\Alias;
use Phil\Enums\Traits\AsParsableEnumTrait;

/**
 * Which way a list is sorted, the same in a query string, a repository, a collection and SQL: `asc` or `desc`, read
 * leniently — `ASC`, `ascending`, `up` — by `parse()`.
 *
 * ```php
 * $direction = SortDirection::parse($request->query->getString('order', 'asc'), ignoreCase: true);
 * $query->orderBy('event.startsAt', $direction->sql());
 * usort($events, static fn (Event $a, Event $b): int => $direction->compare($a->startsAt, $b->startsAt));
 * ```
 */
enum SortDirection: string
{
    use AsParsableEnumTrait;

    #[Alias('ascending'), Alias('up')]
    case Ascending = 'asc';

    #[Alias('descending'), Alias('down')]
    case Descending = 'desc';

    public static function descendingIf(bool $descending): self
    {
        return $descending ? self::Descending : self::Ascending;
    }

    public function isDescending(): bool
    {
        return self::Descending === $this;
    }

    public function reverse(): self
    {
        return $this->isDescending() ? self::Ascending : self::Descending;
    }

    /**
     * The keyword of an SQL or DQL `ORDER BY`, and of Doctrine's `findBy()`.
     *
     * @return 'ASC'|'DESC'
     */
    public function sql(): string
    {
        return $this->isDescending() ? 'DESC' : 'ASC';
    }

    /**
     * The comparison of two values in this direction, for `usort()`.
     */
    public function compare(mixed $a, mixed $b): int
    {
        return $this->isDescending() ? $b <=> $a : $a <=> $b;
    }
}
