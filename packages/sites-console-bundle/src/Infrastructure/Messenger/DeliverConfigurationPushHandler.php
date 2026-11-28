<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Messenger;

use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Message\DeliverConfigurationPush;

/**
 * Delivers a configuration push in a worker.
 */
final readonly class DeliverConfigurationPushHandler
{
    public function __construct(
        private ConfigurationPushes $pushes,
    ) {
    }

    public function __invoke(DeliverConfigurationPush $message): void
    {
        $this->pushes->deliver($message->pushId, $message->actor);
    }
}
