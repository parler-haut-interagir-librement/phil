<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Console;

use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes a new pair of VAPID keys for Web Push, as the two environment variables the configuration reads: the public
 * one goes in the environment, the private one in the application's secrets. Browsers subscribed with an older public
 * key have to subscribe again.
 */
#[AsCommand(name: 'phil:notifications:vapid-keys', description: 'Writes a new pair of VAPID keys for Web Push.')]
final readonly class VapidKeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $keys = VapidKeys::generate();

        $io->writeln('PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $io->writeln('PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $io->note('Keep the private key in the secrets of the application (bin/console secrets:set PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY). Browsers subscribed with another public key have to subscribe again.');

        return Command::SUCCESS;
    }
}
