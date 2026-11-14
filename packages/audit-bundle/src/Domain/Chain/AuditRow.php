<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A row of the trail: its place in its segment, what it records, the facts it is indexed by, the serialization its
 * payload was written in, the hash of the row before it, and its own hash over all of that. Changing any of it, or the
 * row before, shows.
 */
final readonly class AuditRow
{
    /** The hash a segment's first row links to. */
    public const string GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** The text form of an instant: UTC, to the microsecond. */
    public const string INSTANT = 'Y-m-d\TH:i:s.u\Z';

    private function __construct(
        public string $segment,
        public int $sequence,
        public RowKind $kind,
        public DateTimeImmutable $recordedAt,
        public string $version,
        public string $payload,
        public string $previousHash,
        public string $hash,
        public Facts $facts,
    ) {
    }

    /**
     * The row after the head of its segment, or the first one.
     */
    public static function append(?self $head, string $segment, RowKind $kind, DateTimeImmutable $at, string $version, string $payload, ?Facts $facts = null): self
    {
        $facts ??= Facts::none();

        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $segment)) {
            throw InvalidValue::because(\sprintf('A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "%s" is not.', $segment));
        }

        if ($head instanceof self && $head->segment !== $segment) {
            throw InvalidValue::because(\sprintf('A row of "%s" cannot follow the head of "%s".', $segment, $head->segment));
        }

        $sequence = $head instanceof self ? $head->sequence + 1 : 1;
        $previous = $head instanceof self ? $head->hash : self::GENESIS;
        $at = $at->setTimezone(new DateTimeZone('UTC'));

        return new self($segment, $sequence, $kind, $at, $version, $payload, $previous, self::hashOf($segment, $sequence, $kind, $at, $version, $payload, $previous, $facts), $facts);
    }

    /**
     * A row as it was stored, its hash taken as stored: verification recomputes it.
     */
    public static function restore(string $segment, int $sequence, RowKind $kind, DateTimeImmutable $recordedAt, string $version, string $payload, string $previousHash, string $hash, ?Facts $facts = null): self
    {
        return new self($segment, $sequence, $kind, $recordedAt->setTimezone(new DateTimeZone('UTC')), $version, $payload, $previousHash, $hash, $facts ?? Facts::none());
    }

    /**
     * Whether the stored hash is the one its fields give, and every field but the payload is on one line: a line break
     * stored in one of them could shift the lines the hash is taken over and keep it.
     */
    public function isIntact(): bool
    {
        return !str_contains($this->segment, "\n")
            && !str_contains($this->version, "\n")
            && $this->facts->isWellFormed()
            && hash_equals(self::hashOf($this->segment, $this->sequence, $this->kind, $this->recordedAt, $this->version, $this->payload, $this->previousHash, $this->facts), $this->hash);
    }

    /**
     * The instant as the hash and the table write it: UTC, to the microsecond, so that text order is time order.
     */
    public function instant(): string
    {
        return $this->recordedAt->format(self::INSTANT);
    }

    /**
     * SHA-256, in hexadecimal, of every field on a line of its own — the facts after the previous hash, an empty line
     * for each one missing —, the payload last: no field can run into the next, since none of the others holds a line
     * break.
     */
    private static function hashOf(string $segment, int $sequence, RowKind $kind, DateTimeImmutable $at, string $version, string $payload, string $previous, Facts $facts): string
    {
        return hash('sha256', implode("\n", [$segment, $sequence, $kind->value, $at->format(self::INSTANT), $version, $previous, ...$facts->hashLines(), $payload]));
    }
}
