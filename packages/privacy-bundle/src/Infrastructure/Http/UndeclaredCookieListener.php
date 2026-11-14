<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Warns, in the log, of a response that sets a cookie the inventory does not declare: in development by default
 * (`cookies.watch`), where a new cookie shows up before a test is written for it. It never changes the response.
 */
final readonly class UndeclaredCookieListener
{
    public function __construct(
        private CookieInventory $inventory,
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $undeclared = UndeclaredCookies::in($event->getResponse(), $this->inventory);

        if ([] !== $undeclared) {
            $this->logger->warning('The response sets cookies the inventory does not declare: {cookies}.', ['cookies' => implode(', ', $undeclared)]);
        }
    }
}
