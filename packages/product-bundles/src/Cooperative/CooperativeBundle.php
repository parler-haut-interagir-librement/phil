<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Cooperative;

use DateTimeImmutable;
use Phil\Money\Money;
use Phil\Pricing\Rate;
use Phil\ProductBundles\Exception\CooperativeRefused;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Internal\Check;

/**
 * A bundle several sellers put together: a host invites the participants, each with a relative weight, and every
 * participant approves before it is sold. The first approval locks the participants and their weights, so that nobody
 * approves one split and is paid by another; a charity may take a share of every sale first.
 *
 * The bundle reckons each participant's part of a revenue; paying it is the application's work.
 */
final readonly class CooperativeBundle
{
    /**
     * @param array<array-key, int>               $weights   by participant, in the order they were invited
     * @param array<array-key, DateTimeImmutable> $approvals by participant
     */
    private function __construct(
        public string $sku,
        public string $host,
        public array $weights,
        public Rate $charity,
        public array $approvals,
        public ?DateTimeImmutable $lockedAt,
    ) {
    }

    /**
     * A draft: the host and the others, 2 to 50 participants, each weighing 1 to 10 000; the charity takes 0 to 100 %.
     *
     * @param array<array-key, int> $weights by participant, the host among them
     */
    public static function propose(string $sku, string $host, array $weights, ?Rate $charity = null): self
    {
        $charity ??= Rate::zero();
        Check::sku('bundle SKU', $sku);
        Check::opaque('host', $host);
        Check::between('number of participants', \count($weights), 2, Check::MAX_PARTICIPANTS);
        Check::between('charity share', $charity->partsPerMillion, 0, Rate::ONE_MILLION);
        $checked = [];

        foreach ($weights as $participant => $weight) {
            $checked[Check::opaque('participant', (string) $participant)] = Check::between('weight of a participant', $weight, 1, Check::MAX_WEIGHT);
        }

        if (!isset($checked[$host])) {
            throw InvalidBundle::because('host', 'is one of the participants');
        }

        return new self($sku, $host, $checked, $charity, [], null);
    }

    public function isLocked(): bool
    {
        return $this->lockedAt instanceof DateTimeImmutable;
    }

    /**
     * Every participant approved: the bundle may be sold.
     */
    public function isOnSale(): bool
    {
        return \count($this->approvals) === \count($this->weights);
    }

    /**
     * @return list<string>
     */
    public function participants(): array
    {
        return array_map(strval(...), array_keys($this->weights));
    }

    /**
     * @return list<string> the participants yet to approve
     */
    public function pending(): array
    {
        return array_values(array_filter($this->participants(), fn (string $participant): bool => !isset($this->approvals[$participant])));
    }

    /**
     * Changes the weights, adds or removes participants, before the first approval only.
     *
     * @param array<array-key, int> $weights
     *
     * @throws CooperativeRefused once locked
     */
    public function reweigh(array $weights): self
    {
        $this->refuseWhenLocked('the participants and their weights are locked by the first approval');

        return self::propose($this->sku, $this->host, $weights, $this->charity);
    }

    /**
     * Changes the charity's share, before the first approval only.
     *
     * @throws CooperativeRefused once locked
     */
    public function withCharity(Rate $charity): self
    {
        $this->refuseWhenLocked("the charity's share is locked by the first approval");

        return self::propose($this->sku, $this->host, $this->weights, $charity);
    }

    /**
     * A participant approves the split as it stands; the first approval locks it, and the lock keeps the earliest
     * instant of approval. Approving again changes nothing.
     *
     * @throws CooperativeRefused when the participant does not take part
     */
    public function approve(string $participant, DateTimeImmutable $at): self
    {
        if (!isset($this->weights[$participant])) {
            throw CooperativeRefused::because($this->sku, 'only a participant approves');
        }

        if (isset($this->approvals[$participant])) {
            return $this;
        }

        $approvals = $this->approvals;
        $approvals[$participant] = $at;

        $lockedAt = $this->lockedAt instanceof DateTimeImmutable && $this->lockedAt < $at ? $this->lockedAt : $at;

        return new self($this->sku, $this->host, $this->weights, $this->charity, $approvals, $lockedAt);
    }

    /**
     * A revenue shared out: the charity's part first, then the rest by weight, in the order the participants were
     * invited; the parts add up to the revenue to the unit.
     *
     * @return list<CooperativeShare>
     *
     * @throws CooperativeRefused before every participant approved
     */
    public function shares(Money $revenue): array
    {
        if (!$this->isOnSale()) {
            throw CooperativeRefused::because($this->sku, 'shares are reckoned once every participant approved');
        }

        [$charity, $rest] = $revenue->allocate($this->charity->partsPerMillion, Rate::ONE_MILLION - $this->charity->partsPerMillion);
        $shares = 0 === $this->charity->partsPerMillion ? [] : [new CooperativeShare(null, $charity)];

        $amounts = $rest->allocate(...array_values($this->weights));

        foreach ($this->participants() as $index => $participant) {
            $shares[] = new CooperativeShare($participant, $amounts[$index]);
        }

        return $shares;
    }

    /**
     * Scalars and nulls only, instants as Unix timestamps.
     *
     * @return array{sku: string, host: string, charity_ppm: int, participants: list<array{id: string, weight: int, approved_at: int|null}>, locked_at: int|null}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'host' => $this->host,
            'charity_ppm' => $this->charity->partsPerMillion,
            'participants' => array_map(fn (string $participant): array => [
                'id' => $participant,
                'weight' => $this->weights[$participant],
                'approved_at' => isset($this->approvals[$participant]) ? $this->approvals[$participant]->getTimestamp() : null,
            ], $this->participants()),
            'locked_at' => $this->lockedAt?->getTimestamp(),
        ];
    }

    /**
     * Reads back what `toArray()` gave, refusing a row out of shape with `InvalidBundle`.
     *
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $weights = [];
        $approvals = [];
        $first = null;

        foreach (Check::list($row, 'participants', Check::MAX_PARTICIPANTS) as $entry) {
            $entry = Check::row($entry, 'stored participant');
            $participant = Check::string($entry, 'id');

            if (isset($weights[$participant])) {
                throw InvalidBundle::because('stored participant "'.$participant.'"', 'appears once');
            }

            $weights[$participant] = Check::int($entry, 'weight');
            $approvedAt = Check::nullableInt($entry, 'approved_at');

            if (null !== $approvedAt) {
                $approvals[$participant] = self::instant($approvedAt);
                $first = null === $first ? $approvedAt : min($first, $approvedAt);
            }
        }

        $draft = self::propose(Check::string($row, 'sku'), Check::string($row, 'host'), $weights, Rate::of(Check::int($row, 'charity_ppm')));
        $lockedAt = Check::nullableInt($row, 'locked_at');

        if ($lockedAt !== $first) {
            throw InvalidBundle::because('stored lock', 'is the instant of the first approval');
        }

        return new self($draft->sku, $draft->host, $draft->weights, $draft->charity, $approvals, null === $lockedAt ? null : self::instant($lockedAt));
    }

    private function refuseWhenLocked(string $reason): void
    {
        if ($this->isLocked()) {
            throw CooperativeRefused::because($this->sku, $reason);
        }
    }

    private static function instant(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.Check::between('stored instant', $timestamp, 0, 253_402_300_799));
    }
}
