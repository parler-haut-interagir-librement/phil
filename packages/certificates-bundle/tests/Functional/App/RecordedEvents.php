<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional\App;

use Phil\CertificatesBundle\Domain\Event\CertificateAwarded;
use Phil\CertificatesBundle\Domain\Event\CertificateRevoked;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The events of the certificates' lifecycle the application's dispatcher delivered, in order.
 */
final class RecordedEvents
{
    /** @var list<CertificateAwarded|CertificateRevoked> */
    public array $events = [];

    #[AsEventListener]
    public function awarded(CertificateAwarded $event): void
    {
        $this->events[] = $event;
    }

    #[AsEventListener]
    public function revoked(CertificateRevoked $event): void
    {
        $this->events[] = $event;
    }
}
