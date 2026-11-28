<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Message;

/**
 * Deliver a configuration push to the sites it has not reached yet.
 */
final readonly class DeliverConfigurationPush
{
    /**
     * @param ?string $actor the user identifier of the operator who asked, null for a scheduled run
     */
    public function __construct(
        public string $pushId,
        public ?string $actor = null,
    ) {
    }
}
