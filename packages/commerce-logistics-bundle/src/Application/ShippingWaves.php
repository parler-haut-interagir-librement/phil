<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\WaveAssignment;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingWaveRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Shipping waves: orders planned to leave together on an estimated day, which the shop shows the buyer and moves when
 * the goods come late, then releases. An order is in one wave at most; once its wave is released, it stays there.
 * Nothing is committed here.
 */
final readonly class ShippingWaves
{
    public function __construct(
        private ShippingWaveRepository $waves,
    ) {
    }

    public function plan(string $code, string $name, DateTimeImmutable $estimatedOn): ShippingWave
    {
        if ($this->waves->findWave($code) instanceof ShippingWave) {
            throw InvalidValue::because(\sprintf('The wave %s already exists.', $code));
        }

        $wave = ShippingWave::plan($code, $name, $estimatedOn);
        $this->waves->addWave($wave);

        return $wave;
    }

    public function reschedule(string $code, DateTimeImmutable $estimatedOn): void
    {
        $this->wave($code)->reschedule($estimatedOn);
    }

    /**
     * Plans an order in a wave, moving it out of the one it was in.
     */
    public function assign(string $orderNumber, string $code): void
    {
        $wave = $this->wave($code);

        if ($wave->isReleased()) {
            throw StockRefused::waveReleased($code);
        }

        $assignment = $this->waves->assignmentOf($orderNumber);

        if (!$assignment instanceof WaveAssignment) {
            $this->waves->addAssignment(WaveAssignment::of($orderNumber, $wave));

            return;
        }

        $this->checkMovable($assignment);
        $assignment->moveTo($wave);
    }

    /**
     * Takes an order out of its wave: it ships on its own.
     */
    public function withdraw(string $orderNumber): void
    {
        $assignment = $this->waves->assignmentOf($orderNumber);

        if ($assignment instanceof WaveAssignment) {
            $this->checkMovable($assignment);
            $this->waves->removeAssignment($assignment);
        }
    }

    public function waveOf(string $orderNumber): ?ShippingWave
    {
        $assignment = $this->waves->assignmentOf($orderNumber);

        return $assignment instanceof WaveAssignment ? $this->waves->findWave($assignment->wave()) : null;
    }

    /**
     * The day the order's wave is estimated to ship; none for an order in no wave.
     */
    public function estimatedOn(string $orderNumber): ?DateTimeImmutable
    {
        return $this->waveOf($orderNumber)?->estimatedOn();
    }

    /**
     * Releases a wave: its orders are ready to ship.
     *
     * @return list<string> the numbers of its orders
     */
    public function release(string $code, DateTimeImmutable $at): array
    {
        $this->wave($code)->release($at);

        return $this->waves->ordersOfWave($code);
    }

    /**
     * @return list<ShippingWave> the waves not released yet, the earliest estimated first
     */
    public function planned(): array
    {
        return $this->waves->plannedWaves();
    }

    /**
     * @return list<string> the numbers of the orders planned in the wave
     */
    public function ordersOf(string $code): array
    {
        return $this->waves->ordersOfWave($code);
    }

    private function wave(string $code): ShippingWave
    {
        return $this->waves->findWave($code) ?? throw StockRefused::unknownWave($code);
    }

    private function checkMovable(WaveAssignment $assignment): void
    {
        if (true === $this->waves->findWave($assignment->wave())?->isReleased()) {
            throw StockRefused::waveReleased($assignment->wave());
        }
    }
}
