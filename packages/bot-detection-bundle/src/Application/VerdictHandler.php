<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Application;

use Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter;
use Phil\BotDetectionBundle\Domain\Friction\Friction;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;

/**
 * Decides one request from its verdict: counts a robot verdict in aggregate, and returns the friction to
 * apply. The verdict goes no further: the caller applies the friction to the request and drops the verdict,
 * without writing it to the request log, the session or anything keyed on a person.
 */
final readonly class VerdictHandler
{
    public function __construct(
        private FrictionPolicy $policy,
        private RobotVerdictCounter $counter,
    ) {
    }

    public function handle(Verdict $verdict): Friction
    {
        if ($verdict->isRobot()) {
            $this->counter->increment();
        }

        return $this->policy->frictionFor($verdict);
    }
}
