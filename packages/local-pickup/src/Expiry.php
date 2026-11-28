<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Closure;
use DateTimeImmutable;
use Phil\LocalPickup\Contract\ExpiredPickupCanceller;
use Phil\LocalPickup\Contract\ExpiredPickupRestocker;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;

/**
 * Ends the pickups nobody collected within their hold period, and does with each what the application configured:
 * cancel and refund the order, or put its goods back in stock.
 *
 * The configured port is called before the pickup is saved as expired: a run stopped between the two calls the port
 * again for the same order on the next run, which is why the ports are idempotent. Run it from a schedule; each run
 * reads a bounded number of pickups.
 *
 * The service keeps no state between calls.
 */
final readonly class Expiry
{
    public const int MAX_BATCH = 1_000;

    /** @var Closure(Pickup): void */
    private Closure $handle;

    public function __construct(
        private PickupRepository $pickups,
        ExpiryOutcome $outcome,
        ?ExpiredPickupCanceller $canceller = null,
        ?ExpiredPickupRestocker $restocker = null,
    ) {
        $this->handle = match ($outcome) {
            ExpiryOutcome::CancelAndRefund => ($canceller ?? throw InvalidPickup::because('expiry', 'that cancels and refunds is given an ExpiredPickupCanceller'))->cancelAndRefund(...),
            ExpiryOutcome::Restock => ($restocker ?? throw InvalidPickup::because('expiry', 'that restocks is given an ExpiredPickupRestocker'))->restock(...),
        };
    }

    /**
     * Expires the overdue pickups, at most so many, and gives their order numbers.
     *
     * @return list<string>
     */
    public function run(DateTimeImmutable $now, int $limit = 100): array
    {
        Check::range('batch size', $limit, 1, self::MAX_BATCH);
        $expired = [];

        foreach ($this->pickups->overdue($now, $limit) as $order) {
            $pickup = $this->pickups->find($order);

            if (!$pickup instanceof Pickup || !$pickup->overdue($now)) {
                continue;
            }

            ($this->handle)($pickup);

            $pickup->expire($now);
            $this->pickups->save($pickup);
            $expired[] = $order;
        }

        return $expired;
    }
}
