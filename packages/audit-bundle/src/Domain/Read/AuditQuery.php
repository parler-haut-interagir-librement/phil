<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

use DateTimeImmutable;
use DateTimeZone;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What to read of the log: filters that all apply, an order, a page size and where the page starts. Each filter is
 * an equality on an indexed column, the action may also be a prefix, and the period is half-open: from `since`
 * included to `until` excluded. Immutable: each method returns a new query.
 */
final class AuditQuery
{
    public const int DEFAULT_LIMIT = 50;

    public const int MAX_LIMIT = 500;

    public private(set) ?string $actor = null;

    public private(set) ?string $action = null;

    public private(set) bool $actionIsPrefix = false;

    public private(set) ?string $subjectType = null;

    public private(set) ?string $subjectId = null;

    public private(set) ?string $segment = null;

    public private(set) ?string $tenant = null;

    public private(set) ?DateTimeImmutable $since = null;

    public private(set) ?DateTimeImmutable $until = null;

    public private(set) Order $order = Order::NewestFirst;

    public private(set) int $limit = self::DEFAULT_LIMIT;

    public private(set) ?Cursor $after = null;

    private function __construct()
    {
    }

    /**
     * Every row, the latest first, a page of {@see self::DEFAULT_LIMIT}.
     */
    public static function all(): self
    {
        return new self();
    }

    /**
     * The history of one record: its rows, the earliest first.
     */
    public static function historyOf(Subject $subject): self
    {
        return new self()->about($subject)->ordered(Order::OldestFirst);
    }

    public function byActor(string $actor): self
    {
        Facts::assertIdentifier('An actor', $actor);

        $query = clone $this;
        $query->actor = $actor;

        return $query;
    }

    /**
     * Rows of that action; with `$prefix`, of every action that starts with it, such as `workflow.order.`.
     */
    public function withAction(string $action, bool $prefix = false): self
    {
        Facts::assertAction($action);

        $query = clone $this;
        $query->action = $action;
        $query->actionIsPrefix = $prefix;

        return $query;
    }

    /**
     * The reads only: rows whose action starts with {@see Facts::READ_PREFIX}.
     */
    public function reads(): self
    {
        return $this->withAction(Facts::READ_PREFIX, true);
    }

    public function about(Subject $subject): self
    {
        $query = clone $this;
        $query->subjectType = $subject->type;
        $query->subjectId = $subject->id;

        return $query;
    }

    /**
     * Rows about any record of that type.
     */
    public function aboutType(string $type): self
    {
        Subject::assertType($type);
        $query = clone $this;
        $query->subjectType = $type;
        $query->subjectId = null;

        return $query;
    }

    public function inSegment(string $segment): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $segment)) {
            throw InvalidValue::because(\sprintf('A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "%s" is not.', $segment));
        }

        $query = clone $this;
        $query->segment = $segment;

        return $query;
    }

    /**
     * Rows of one tenant; with `null`, of every tenant and of none.
     */
    public function inTenant(?string $tenant): self
    {
        if (null !== $tenant) {
            Facts::assertTenant($tenant);
        }

        $query = clone $this;
        $query->tenant = $tenant;

        return $query;
    }

    /**
     * Rows recorded from `since` included to `until` excluded; either end may be open.
     *
     * @throws InvalidValue when the period ends before it starts
     */
    public function between(?DateTimeImmutable $since, ?DateTimeImmutable $until): self
    {
        $utc = new DateTimeZone('UTC');
        $since = $since?->setTimezone($utc);
        $until = $until?->setTimezone($utc);

        if ($since instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until < $since) {
            throw InvalidValue::because('The period ends before it starts.');
        }

        $query = clone $this;
        $query->since = $since;
        $query->until = $until;

        return $query;
    }

    public function ordered(Order $order): self
    {
        $query = clone $this;
        $query->order = $order;

        return $query;
    }

    /**
     * @throws InvalidValue when the limit is not between 1 and {@see self::MAX_LIMIT}
     */
    public function limitedTo(int $limit): self
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidValue::because(\sprintf('A page holds 1 to %d rows; %d is not.', self::MAX_LIMIT, $limit));
        }

        $query = clone $this;
        $query->limit = $limit;

        return $query;
    }

    /**
     * The page after this cursor; with `null`, the first page.
     */
    public function after(?Cursor $cursor): self
    {
        $query = clone $this;
        $query->after = $cursor;

        return $query;
    }

    /**
     * The instant `since` as the table keeps it, `null` when the period is open at its start.
     */
    public function sinceInstant(): ?string
    {
        return $this->since?->format(AuditRow::INSTANT);
    }

    /**
     * The instant `until` as the table keeps it, `null` when the period is open at its end.
     */
    public function untilInstant(): ?string
    {
        return $this->until?->format(AuditRow::INSTANT);
    }

    /**
     * Whether a row passes every filter of the query, its cursor aside: what an adapter that cannot filter in its
     * storage applies to each row it reads.
     */
    public function matches(AuditRow $row): bool
    {
        $facts = $row->facts;
        $instant = $row->instant();

        return (null === $this->actor || $this->actor === $facts->actor)
            && (null === $this->action || ($this->actionIsPrefix ? str_starts_with($facts->action ?? '', $this->action) : $this->action === $facts->action))
            && (null === $this->subjectType || $this->subjectType === $facts->subject?->type)
            && (null === $this->subjectId || $this->subjectId === $facts->subject?->id)
            && (null === $this->segment || $this->segment === $row->segment)
            && (null === $this->tenant || $this->tenant === $facts->tenant)
            && (null === $this->sinceInstant() || $instant >= $this->sinceInstant())
            && (null === $this->untilInstant() || $instant < $this->untilInstant());
    }

    /**
     * Whether a row comes after the cursor in the query's order; every row does without a cursor.
     */
    public function isAfterCursor(AuditRow $row): bool
    {
        if (!$this->after instanceof Cursor) {
            return true;
        }

        $comparison = [$row->instant(), $row->segment, $row->sequence] <=> [$this->after->instant, $this->after->segment, $this->after->sequence];

        return Order::NewestFirst === $this->order ? $comparison < 0 : $comparison > 0;
    }
}
