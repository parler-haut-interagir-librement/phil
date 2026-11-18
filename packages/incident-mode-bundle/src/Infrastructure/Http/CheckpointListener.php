<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Http;

use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Exception\SuspendedByIncident;
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * The checkpoints of the requests: a controller that carries `#[SuspendedDuring]`, or a route whose defaults name
 * restrictions under `_suspended_during` — for the routes of another bundle, which carry no attribute of this one —,
 * is refused while one of them applies to the member of the request. The refusal is rendered as the page of the
 * suspension when Twig is there.
 */
final readonly class CheckpointListener
{
    /** The route default that names the restrictions of a route, a name or a list of names. */
    public const string ROUTE_DEFAULT = '_suspended_during';

    public function __construct(
        private Checkpoint $checkpoint,
        private ?SuspensionPage $page = null,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $restrictions = [];

        foreach ($event->getAttributes(SuspendedDuring::class) as $attribute) {
            if ($attribute->covers($request->getMethod())) {
                $restrictions = [...$restrictions, ...$attribute->restrictions];
            }
        }

        $route = $request->attributes->get(self::ROUTE_DEFAULT);

        foreach (\is_array($route) ? $route : [$route] as $restriction) {
            if (\is_string($restriction) && '' !== $restriction) {
                $restrictions[] = $restriction;
            }
        }

        if ([] !== $restrictions) {
            $this->checkpoint->guard(...$restrictions);
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $suspended = $event->getThrowable();

        if ($suspended instanceof SuspendedByIncident && $this->page instanceof SuspensionPage) {
            $event->setResponse($this->page->refusal($suspended));
        }
    }
}
