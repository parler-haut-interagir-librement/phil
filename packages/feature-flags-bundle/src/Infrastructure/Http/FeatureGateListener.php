<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Http;

use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Answers 404 for a controller whose {@see IsFeatureEnabled} flag is off, or whose variant the context is not in.
 * A 404 rather than a 403: a feature that is not released does not exist yet, and saying it is forbidden would
 * say that it does.
 */
final readonly class FeatureGateListener
{
    public function __construct(
        private FlagEvaluator $flags,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        foreach ($event->getAttributes(IsFeatureEnabled::class) as $gate) {
            if (!$this->opens($gate)) {
                throw new NotFoundHttpException();
            }
        }
    }

    private function opens(IsFeatureEnabled $gate): bool
    {
        $evaluation = $this->flags->evaluate(FlagName::of($gate->flag));

        if (null === $gate->variant) {
            return $evaluation->enabled;
        }

        return $evaluation->variant?->value === $gate->variant;
    }
}
