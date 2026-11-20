<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Port\PrintOnDemand;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\CommerceLogisticsBundle\Domain\Repository\ConventionRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The stock of convention items — printed shirts, one SKU per size —, prepared per convention by the quantities
 * entered for each SKU, and the route of an order placed online:
 *
 * - **a convention is near** — one of its days falls within the next `windowDays` days, 30 by default —: its items go
 *   to print, and the stock stays for the convention;
 * - **none is near**: an item is taken from stock when enough of it is available over the open locations, and goes to
 *   print otherwise.
 *
 * An order counts as placed online when it has no channel or its channel is one of `onlineChannels`; an order of
 * another channel — the till at the convention — takes from stock as any order. A SKU never prepared for a convention
 * is not a convention item and is reserved as usual.
 */
final readonly class ConventionStock
{
    /** The most days the window looks ahead. */
    public const int MAX_WINDOW_DAYS = 366;

    /**
     * @param list<string>            $onlineChannels the channels whose orders are placed online, beside orders without one
     * @param iterable<PrintOnDemand> $printers
     */
    public function __construct(
        private ConventionRepository $conventions,
        private LocationRepository $locations,
        private StockRepository $stock,
        private int $windowDays = 30,
        private array $onlineChannels = [],
        private iterable $printers = [],
    ) {
        if ($windowDays < 0 || $windowDays > self::MAX_WINDOW_DAYS) {
            throw InvalidValue::because(\sprintf('A convention is near within 0 to %d days; %d is refused.', self::MAX_WINDOW_DAYS, $windowDays));
        }
    }

    /**
     * Adds a convention to the calendar, or moves the days of one already there.
     */
    public function schedule(string $code, string $name, DateTimeImmutable $startsOn, DateTimeImmutable $endsOn): Convention
    {
        $convention = $this->conventions->findConvention($code);

        if ($convention instanceof Convention) {
            $convention->reschedule($startsOn, $endsOn);

            return $convention;
        }

        $convention = Convention::of($code, $name, $startsOn, $endsOn);
        $this->conventions->addConvention($convention);

        return $convention;
    }

    /**
     * Enters how many units of a SKU are prepared for a convention; zero keeps the SKU a convention item.
     */
    public function enter(string $convention, string $sku, int $quantity): void
    {
        $entered = $this->conventions->quantity($convention, $sku);

        if ($entered instanceof ConventionQuantity) {
            $entered->change($quantity);

            return;
        }

        $this->conventions->addQuantity(ConventionQuantity::of($this->conventions->findConvention($convention) ?? throw StockRefused::unknownConvention($convention), $sku, $quantity));
    }

    /**
     * @return array<int|string, int> SKU → units prepared for the convention; a numeric SKU is an integer key
     */
    public function planned(string $convention): array
    {
        $planned = [];

        foreach ($this->conventions->quantitiesOf($convention) as $quantity) {
            $planned[$quantity->sku] = $quantity->quantity();
        }

        return $planned;
    }

    /**
     * What is left to print for a convention: the units prepared beyond those available now over the open locations.
     *
     * @return array<int|string, int> SKU → units still to print, only those above zero; a numeric SKU is an integer key
     */
    public function shortfall(string $convention): array
    {
        $planned = $this->conventions->quantitiesOf($convention);
        $available = $this->available(array_map(static fn (ConventionQuantity $quantity): string => $quantity->sku, $planned));
        $shortfall = [];

        foreach ($planned as $quantity) {
            $missing = $quantity->quantity() - ($available[$quantity->sku] ?? 0);

            if ($missing > 0) {
                $shortfall[$quantity->sku] = $missing;
            }
        }

        return $shortfall;
    }

    /**
     * @return list<Convention> the conventions with a day within the window from that moment, the earliest first
     */
    public function near(DateTimeImmutable $at): array
    {
        return $this->conventions->conventionsMeeting($at, $at->modify(\sprintf('+%d days', $this->windowDays)));
    }

    /**
     * Whether some of the order's units already went to print: its route is decided.
     */
    public function routed(string $orderNumber): bool
    {
        return [] !== $this->conventions->printJobsOf($orderNumber);
    }

    /**
     * The convention items of an order placed online that go to print rather than come from stock. Nothing is
     * recorded: `print()` does, once the rest of the order is reserved.
     *
     * @param array<int|string, int> $wanted SKU → units the order holds; a numeric SKU is an integer key
     *
     * @return array<int|string, int> SKU → units to print; a numeric SKU is an integer key
     */
    public function toPrint(Order $order, array $wanted, DateTimeImmutable $at): array
    {
        $channel = $order->channel();

        if (null !== $channel && !\in_array($channel, $this->onlineChannels, true)) {
            return [];
        }

        $items = $this->conventions->conventionSkus(array_map(strval(...), array_keys($wanted)));

        if ([] === $items) {
            return [];
        }

        $printed = [];
        $near = [] !== $this->near($at);
        $available = $near ? [] : $this->available($items);

        foreach ($items as $sku) {
            if ($near || ($available[$sku] ?? 0) < $wanted[$sku]) {
                $printed[$sku] = $wanted[$sku];
            }
        }

        return $printed;
    }

    /**
     * Records the units sent to print and hands each to the `PrintOnDemand` services, once per order and SKU.
     *
     * @param array<int|string, int> $printed SKU → units to print; a numeric SKU is an integer key
     */
    public function print(string $orderNumber, array $printed, DateTimeImmutable $at): void
    {
        $sent = array_flip(array_map(static fn (PrintJob $job): string => $job->sku, $this->conventions->printJobsOf($orderNumber)));

        foreach ($printed as $sku => $quantity) {
            $sku = (string) $sku;

            if (isset($sent[$sku])) {
                continue;
            }

            $this->conventions->addPrintJob(PrintJob::of($orderNumber, $sku, $quantity, $at));

            foreach ($this->printers as $printer) {
                $printer->print($orderNumber, $sku, $quantity);
            }
        }
    }

    /**
     * @param list<string> $skus
     *
     * @return array<string, int> SKU → units available over the open locations
     */
    private function available(array $skus): array
    {
        if ([] === $skus) {
            return [];
        }

        $open = array_flip(array_map(static fn (Location $location): string => $location->code, $this->locations->openLocations()));
        $available = [];

        foreach ($this->stock->levelsOfSkus($skus) as $level) {
            if (isset($open[$level->location])) {
                $available[$level->sku] = ($available[$level->sku] ?? 0) + $level->available();
            }
        }

        return $available;
    }
}
