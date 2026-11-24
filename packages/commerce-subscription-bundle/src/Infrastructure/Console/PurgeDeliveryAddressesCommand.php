<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Console;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Purges the delivery addresses past their retention, now, under the same lock as the schedule when a `LockFactory`
 * is given: a purge already running elsewhere is left to finish.
 */
#[AsCommand(name: 'phil:commerce-subscription:purge-addresses', description: 'Erases the delivery addresses kept past their retention')]
final readonly class PurgeDeliveryAddressesCommand
{
    public const string LOCK = 'phil_commerce_subscription_purge_addresses';

    public function __construct(
        private DeliveryAddresses $addresses,
        private ?LockFactory $locks = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'How many addresses and copies each batch erases')]
        int $limit = 500,
    ): int {
        if ($limit < 1 || $limit > 10_000) {
            $io->error('The limit is 1 to 10000.');

            return Command::INVALID;
        }

        $lock = $this->locks?->createLock(self::LOCK);

        if ($lock instanceof SharedLockInterface && !$lock->acquire()) {
            $io->warning('A purge of the delivery addresses is already running.');

            return Command::SUCCESS;
        }

        try {
            $erased = $this->addresses->purge($this->clock?->now() ?? new DateTimeImmutable(), $limit);
        } finally {
            $lock?->release();
        }

        $io->success(\sprintf('%d addresses of subscriptions and %d copies in orders erased, kept %d days after their last shipment.', $erased['addresses'], $erased['copies'], $this->addresses->retentionDays()));

        return Command::SUCCESS;
    }
}
