<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use DateTimeImmutable;
use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * One incident: the preset it was started from, what it suspends, when it started and when it ends, who started it
 * and why; once over, when, by whom and why it ended.
 *
 * It is in force from its start until its end or its lifting, whichever comes first. The restrictions are copied from
 * the preset at the start, so that changing a preset leaves the incidents in force as they were started.
 */
final readonly class Activation
{
    public const int MAX_REASON_LENGTH = 500;

    /** The length of an identifier: 32 hexadecimal characters. */
    public const int ID_LENGTH = 32;

    /**
     * @param list<Restriction> $restrictions at least one
     */
    public function __construct(
        public string $id,
        public string $preset,
        public array $restrictions,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $endsAt,
        public ?Actor $startedBy,
        public ?string $reason = null,
        public ?DateTimeImmutable $liftedAt = null,
        public ?Actor $liftedBy = null,
        public ?LiftReason $liftReason = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidIncident::because('An activation is identified by 32 lowercase hexadecimal characters.');
        }

        if (1 !== preg_match(Restriction::PATTERN, $preset)) {
            throw InvalidIncident::because(\sprintf('"%s" is not a preset name.', $preset));
        }

        if ([] === $restrictions) {
            throw InvalidIncident::because('An activation suspends at least one thing.');
        }

        if ($endsAt <= $startedAt) {
            throw InvalidIncident::because('An activation ends after it starts.');
        }

        if (null !== $reason && mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw InvalidIncident::because(\sprintf('A reason has at most %d characters.', self::MAX_REASON_LENGTH));
        }

        if ((!$liftedAt instanceof DateTimeImmutable) !== (!$liftReason instanceof LiftReason)) {
            throw InvalidIncident::because('A lifted activation has both the moment and the reason of its lifting.');
        }
    }

    /**
     * A new activation of the preset, starting at the moment given, with a random identifier.
     */
    public static function start(Preset $preset, Duration $duration, DateTimeImmutable $at, ?Actor $by, ?string $reason = null): self
    {
        $reason = null === $reason ? null : trim($reason);

        return new self(sodium_bin2hex(random_bytes(16)), $preset->name, $preset->restrictions, $at, $duration->after($at), $by, '' === $reason ? null : $reason);
    }

    public function isInForceAt(DateTimeImmutable $now): bool
    {
        return !$this->liftedAt instanceof DateTimeImmutable && $now >= $this->startedAt && $now < $this->endsAt;
    }

    /**
     * Past its end and not lifted yet: the automatic lifting has still to record it.
     */
    public function isDueAt(DateTimeImmutable $now): bool
    {
        return !$this->liftedAt instanceof DateTimeImmutable && $now >= $this->endsAt;
    }

    public function suspends(Restriction $restriction): bool
    {
        return array_any($this->restrictions, static fn (Restriction $suspended): bool => $suspended->isReadOnly() || $suspended->name === $restriction->name);
    }

    /**
     * @return list<string>
     */
    public function restrictionNames(): array
    {
        return array_map(static fn (Restriction $restriction): string => $restriction->name, $this->restrictions);
    }

    public function liftedOn(DateTimeImmutable $at, ?Actor $by, LiftReason $reason): self
    {
        return new self($this->id, $this->preset, $this->restrictions, $this->startedAt, $this->endsAt, $this->startedBy, $this->reason, $at, $by, $reason);
    }

    public function endingAt(DateTimeImmutable $endsAt): self
    {
        return new self($this->id, $this->preset, $this->restrictions, $this->startedAt, $endsAt, $this->startedBy, $this->reason, $this->liftedAt, $this->liftedBy, $this->liftReason);
    }
}
