<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Scheduler;

use Override;
use Phil\CommerceSubscriptionBundle\Application\Message\PurgeDeliveryAddresses;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_commerce_subscription_addresses`, run by `messenger:consume
 * scheduler_phil_commerce_subscription_addresses`: a purge of the delivery addresses past their retention at the
 * configured frequency.
 *
 * With a `LockFactory`, the schedule runs under a lock, so that several workers consuming it send each purge once; the
 * purge itself stays idempotent for a message Messenger delivers again.
 */
final class DeliveryAddressSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_commerce_subscription_addresses';

    private ?Schedule $schedule = null;

    public function __construct(
        private readonly string $frequency = '1 day',
        private readonly ?LockFactory $locks = null,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        if ($this->schedule instanceof Schedule) {
            return $this->schedule;
        }

        $schedule = new Schedule()->add(RecurringMessage::every($this->frequency, new PurgeDeliveryAddresses()));

        if ($this->locks instanceof LockFactory) {
            $schedule->lock($this->locks->createLock('scheduler_'.self::NAME));
        }

        return $this->schedule = $schedule;
    }
}
