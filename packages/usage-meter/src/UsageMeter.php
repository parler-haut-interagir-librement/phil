<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\Period\Window;
use Psr\Clock\ClockInterface;

/**
 * The library in one object: what a subject used of a meter, whether an action fits, and the counting itself.
 *
 * `consume()` comes before the action it counts, and refuses it when it would take the subject past its quota;
 * `record()` counts what was already used — tokens a model spent — and never refuses. Both take an idempotency key:
 * a retried request counts once, and `refund()` gives back what a key counted when its action failed afterwards. A subject is the application's opaque reference — an account, a tenant, a project —
 * of 1 to 255 bytes.
 */
final readonly class UsageMeter
{
    public function __construct(
        private Meters $meters,
        private UsageStore $store,
        private ClockInterface $clock,
        private ?LimitProvider $limits = null,
        private ?AnchorProvider $anchors = null,
    ) {
    }

    /**
     * The subject's limit of the meter: the provider's, or the meter's own when the provider names none.
     */
    public function limit(string $subject, string $meter): Limit
    {
        return $this->limitOf($this->subject($subject), $this->meters->get($meter));
    }

    public function usage(string $subject, string $meter): Usage
    {
        $definition = $this->meters->get($meter);
        $subject = $this->subject($subject);

        return $this->read($subject, $definition, $this->windowOf($subject, $definition), $this->limitOf($subject, $definition));
    }

    /**
     * Whether `$amount` more fits now. A check before showing a button; `consume()` decides atomically.
     */
    public function allows(string $subject, string $meter, int $amount = 1): bool
    {
        return $this->usage($subject, $meter)->allows($this->amount($amount));
    }

    /**
     * Counts `$amount` before the action it stands for, unless it would take the subject past its quota.
     *
     * @param string|null $key the action's idempotency key, 1 to 255 bytes: a second call with it counts nothing
     *                         and answers the usage as it is, marked replayed
     *
     * @throws QuotaExceeded when the amount does not fit; nothing was counted
     */
    public function consume(string $subject, string $meter, int $amount = 1, ?string $key = null): Usage
    {
        $definition = $this->meters->get($meter);
        $subject = $this->subject($subject);
        $amount = $this->amount($amount);
        $key = $this->key($key);
        $window = $this->windowOf($subject, $definition);
        $limit = $this->limitOf($subject, $definition);
        $ceiling = null;

        if (null !== $limit->value) {
            // What the earlier buckets of a rolling window hold leaves that much less to the current one; a period
            // has no earlier bucket.
            $ceiling = $limit->value - $this->store->total($subject, $definition->name, $window->start, $window->counterStart);
        }

        $outcome = $this->store->add(new Counter($subject, $definition->name, $window->counterStart, $window->counterEnd), $amount, $ceiling, $key);
        $usage = $this->read($subject, $definition, $window, $limit, Increment::Duplicate === $outcome);

        if (Increment::Refused === $outcome) {
            throw QuotaExceeded::by($usage, $amount);
        }

        return $usage;
    }

    /**
     * Counts `$amount` already used, whatever the quota: the window may end up over its limit, and `consume()` then
     * refuses until it frees enough.
     *
     * @param string|null $key the use's idempotency key, 1 to 255 bytes: a second call with it counts nothing
     */
    public function record(string $subject, string $meter, int $amount = 1, ?string $key = null): Usage
    {
        $definition = $this->meters->get($meter);
        $subject = $this->subject($subject);
        $amount = $this->amount($amount);
        $key = $this->key($key);
        $window = $this->windowOf($subject, $definition);
        $outcome = $this->store->add(new Counter($subject, $definition->name, $window->counterStart, $window->counterEnd), $amount, null, $key);

        return $this->read($subject, $definition, $window, $this->limitOf($subject, $definition), Increment::Duplicate === $outcome);
    }

    /**
     * Gives back what `consume()` or `record()` counted under `$key`, when the action it stood for failed afterwards.
     *
     * Idempotent: `$amount` is what the key's refunds come to in all, bounded by what the key counted — null for all
     * of it —, so that a retried refund gives nothing more back. The amount returns to the counter the key went to.
     *
     * @param string   $key    the idempotency key the amount was counted under, 1 to 255 bytes
     * @param int|null $amount one or more, or null for everything the key counted
     *
     * @return Usage the usage of the current window, marked replayed when nothing was given back: an unknown or
     *               forgotten key, or one refunded that far already
     */
    public function refund(string $subject, string $meter, string $key, ?int $amount = null): Usage
    {
        $definition = $this->meters->get($meter);
        $subject = $this->subject($subject);
        $key = $this->requiredKey($key);
        $given = $this->store->refund($subject, $definition->name, $key, null === $amount ? null : $this->amount($amount));

        return $this->read($subject, $definition, $this->windowOf($subject, $definition), $this->limitOf($subject, $definition), 0 === $given);
    }

    /**
     * Forgets the counters and keys that no window counts any more.
     *
     * @return int how many counters and keys were forgotten
     */
    public function purge(): int
    {
        return $this->store->purge($this->clock->now());
    }

    private function windowOf(string $subject, Meter $meter): Window
    {
        return $meter->period->windowAt($this->clock->now(), $this->anchors?->anchorFor($subject, $meter));
    }

    private function limitOf(string $subject, Meter $meter): Limit
    {
        return $this->limits?->limitFor($subject, $meter) ?? $meter->limit;
    }

    private function read(string $subject, Meter $meter, Window $window, Limit $limit, bool $replayed = false): Usage
    {
        return new Usage($subject, $meter->name, $this->store->total($subject, $meter->name, $window->start, $window->end), $limit, $window->start, $window->end, $replayed);
    }

    private function subject(string $subject): string
    {
        if ('' === $subject || \strlen($subject) > 255) {
            throw InvalidUsage::because('A subject is named by 1 to 255 bytes.');
        }

        return $subject;
    }

    private function amount(int $amount): int
    {
        if ($amount < 1) {
            throw InvalidUsage::because(\sprintf('An amount counted is one or more; got %d.', $amount));
        }

        return $amount;
    }

    private function key(?string $key): ?string
    {
        return null === $key ? null : $this->requiredKey($key);
    }

    private function requiredKey(string $key): string
    {
        if ('' === $key || \strlen($key) > 255) {
            throw InvalidUsage::because('An idempotency key is 1 to 255 bytes.');
        }

        return $key;
    }
}
