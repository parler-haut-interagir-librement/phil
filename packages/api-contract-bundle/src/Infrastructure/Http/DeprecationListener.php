<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\ApiContract\Deprecation\DeprecationSignal;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Announces the deprecation of a controller marked `#[DeprecatedEndpoint]` on each of its responses. `Deprecation`
 * and `Sunset` replace any the controller set; the `Link` with the relation `deprecation` is added beside the
 * response's own `Link` headers — pagination, preload —, never in their place.
 */
final class DeprecationListener
{
    private const string SIGNAL = '_phil_api_contract_deprecation';

    public function onController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes(DeprecatedEndpoint::class);

        if ([] !== $attributes) {
            $event->getRequest()->attributes->set(self::SIGNAL, $attributes[0]->signal());
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $signal = $event->getRequest()->attributes->get(self::SIGNAL);

        if (!$signal instanceof DeprecationSignal) {
            return;
        }

        $headers = $event->getResponse()->headers;

        foreach ($signal->toHeaders() as $name => $value) {
            $headers->set($name, $value, DeprecationSignal::LINK_HEADER !== $name);
        }
    }
}
