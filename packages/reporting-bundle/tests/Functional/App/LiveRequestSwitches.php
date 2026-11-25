<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * What a test turns on around a request of LiveComponent: a 422 raised before the action, as an argument resolver or a
 * controller attribute raises it, or a redirection of the answer, as a firewall that denies sends one. Each test boots
 * a kernel of its own, so a switch stays within its test.
 */
final class LiveRequestSwitches
{
    public bool $refuseBeforeTheAction = false;

    public ?string $redirectTo = null;

    #[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS)]
    public function refuse(ControllerArgumentsEvent $event): void
    {
        if ($this->refuseBeforeTheAction && $event->getRequest()->attributes->has('_live_component')) {
            throw new UnprocessableEntityHttpException('Refused before the action.');
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 16)]
    public function redirect(ResponseEvent $event): void
    {
        if (null !== $this->redirectTo && $event->isMainRequest() && $event->getRequest()->attributes->has('_live_component')) {
            $event->setResponse(new RedirectResponse($this->redirectTo));
        }
    }
}
