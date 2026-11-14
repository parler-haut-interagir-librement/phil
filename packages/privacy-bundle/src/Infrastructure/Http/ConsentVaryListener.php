<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * A page that read the consent differs by visitor: a shared cache must not give one person's page to another.
 */
final class ConsentVaryListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && $event->getRequest()->attributes->has(ConsentContext::ATTRIBUTE)) {
            $event->getResponse()->setVary(['Cookie', 'Sec-GPC'], false);
        }
    }
}
