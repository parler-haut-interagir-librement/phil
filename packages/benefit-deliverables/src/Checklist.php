<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Contract\DeliverableStore;
use Phil\BenefitDeliverables\Contract\EntitlementSource;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * The checklist of a period: one line per benefit owed to each member, checked off one by one or by lot, and the list
 * of who is still to be served.
 *
 * Who is owed what comes from the `EntitlementSource`, what was delivered from the `DeliverableStore`; the checklist
 * reads both a page of members at a time, so that a period of any size stays within bounded memory.
 */
final readonly class Checklist
{
    public const int MAX_PAGE = 1_000;

    public function __construct(
        private EntitlementSource $entitlements,
        private DeliverableStore $deliveries,
    ) {
    }

    /**
     * The lines of up to `$limit` members, after `$after`; only those of `$benefit` when given.
     */
    public function page(Period $period, ?string $benefit = null, ?string $after = null, int $limit = 100): ChecklistPage
    {
        return $this->read($period, $benefit, $after, $limit, false);
    }

    /**
     * The lines not done yet of up to `$limit` members, after `$after`: who is still to be served.
     */
    public function remaining(Period $period, ?string $benefit = null, ?string $after = null, int $limit = 100): ChecklistPage
    {
        return $this->read($period, $benefit, $after, $limit, true);
    }

    /**
     * Marks the benefit done for each member owed it in the period; a member not owed it is left out, a deliverable
     * already done keeps its first delivery.
     *
     * @param list<string> $members at most 1000
     * @param string       $by      the identifier of the person on the team who marks them
     *
     * @return int how many were marked now
     */
    public function markDone(Period $period, string $benefit, array $members, DateTimeImmutable $at, string $by): int
    {
        if (\count($members) > self::MAX_PAGE) {
            throw InvalidDeliverable::because(\sprintf('A lot marks %d deliverables at most.', self::MAX_PAGE));
        }

        if ('' === trim($by) || \strlen($by) > 255) {
            throw InvalidDeliverable::because('Who marks a deliverable is named by 1 to 255 characters.');
        }

        $members = array_values(array_unique(array_map(Member::check(...), $members)));

        if ([] === $members) {
            return 0;
        }

        $keys = [];

        foreach ($this->entitlements->entitlementsOf($period, $members) as $member => $entitlement) {
            if (\in_array($member, $members, true) && $entitlement->benefit($benefit) instanceof Benefit) {
                $keys[] = new DeliverableKey($period, $benefit, $member);
            }
        }

        return [] === $keys ? 0 : $this->deliveries->markDone($keys, $at, $by);
    }

    /**
     * Forgets the delivery of a deliverable marked by mistake; whether it was marked.
     */
    public function unmark(Period $period, string $benefit, string $member): bool
    {
        return $this->deliveries->unmark(new DeliverableKey($period, $benefit, Member::check($member)));
    }

    private function read(Period $period, ?string $benefit, ?string $after, int $limit, bool $remainingOnly): ChecklistPage
    {
        if ($limit < 1 || $limit > self::MAX_PAGE) {
            throw InvalidDeliverable::because(\sprintf('A page of the checklist reads 1 to %d members.', self::MAX_PAGE));
        }

        $entitlements = $this->entitlements->entitlements($period, $after, $limit);
        $members = array_map(static fn (Entitlement $entitlement): string => $entitlement->member, $entitlements);
        $done = [];

        foreach ([] === $members ? [] : $this->deliveries->deliveries($period, $members) as $delivery) {
            if ($delivery->key->period->equals($period)) {
                $done[$delivery->key->member][$delivery->key->benefit] = $delivery;
            }
        }

        $items = [];

        foreach ($entitlements as $entitlement) {
            foreach ($entitlement->benefits as $code => $owed) {
                if (null !== $benefit && $code !== $benefit) {
                    continue;
                }

                $delivery = $done[$entitlement->member][$code] ?? null;

                if (!$remainingOnly || !$delivery instanceof Delivery) {
                    $items[] = new ChecklistItem(new DeliverableKey($period, $code, $entitlement->member), $owed, $delivery);
                }
            }
        }

        $last = array_last($members);

        return new ChecklistPage($items, \count($entitlements) < $limit ? null : $last);
    }
}
