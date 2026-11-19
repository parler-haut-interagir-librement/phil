<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;

/**
 * Where the short links are kept.
 */
interface ShortLinkRepository
{
    public function find(int $id): ?ShortLink;

    /**
     * The link of exactly this code, its case included.
     */
    public function findByCode(string $code): ?ShortLink;

    /**
     * Whether a link holds this code as the database compares it: on a database that compares text without case, as
     * MySQL does by default, a code that differs only by its case is taken too, since its unique index refuses it.
     */
    public function isTaken(string $code): bool;

    public function save(ShortLink $link): void;

    /**
     * @return bool whether a link was removed
     */
    public function remove(int $id): bool;

    /**
     * Counts one visit of the link when, at that moment, it is in force: enabled, inside its validity dates and under
     * its maximum number of visits. A person's visit counts in the visits and moves the last visit; a machine's counts
     * in the robot visits alone, and never uses up the maximum. The check and the count are one statement, so that two
     * requests racing for the last visit count it once.
     *
     * @return bool whether the visit was counted
     */
    public function recordVisit(int $id, DateTimeImmutable $now, VisitorClass $visitor = VisitorClass::Human): bool;

    /**
     * A page of the links, the newest first, whose code, label or target contains the search.
     *
     * @return list<ShortLink>
     */
    public function page(string $search, int $offset, int $limit): array;

    public function count(string $search): int;
}
