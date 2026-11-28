<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Event;

use Phil\SitesConsoleBundle\Domain\ValueObject\Alert;

/**
 * Dispatched once when a pull finds an alert the previous snapshot of the site did not have: listen to it to send a
 * notification.
 */
final readonly class SiteAlertRaised
{
    public function __construct(
        public Alert $alert,
    ) {
    }
}
