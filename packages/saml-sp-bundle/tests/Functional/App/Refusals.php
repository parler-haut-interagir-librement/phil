<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Functional\App;

use Phil\SamlSpBundle\Application\Event\SamlLogoutReceived;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records the refusals and the logouts the bundle announces, for the tests to read.
 */
final class Refusals
{
    /** @var list<string> */
    public array $reasons = [];

    /** @var list<string> */
    public array $logouts = [];

    #[AsEventListener]
    public function onRefused(SamlResponseRefused $event): void
    {
        $this->reasons[] = $event->identityProvider.': '.$event->reason;
    }

    #[AsEventListener]
    public function onLogout(SamlLogoutReceived $event): void
    {
        $this->logouts[] = $event->identityProvider.': '.$event->request->nameId;
    }
}
