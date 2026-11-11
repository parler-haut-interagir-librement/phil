<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Domain\Counter;

/**
 * The aggregate count of robot verdicts, and the only trace a verdict leaves.
 *
 * **`increment()` takes no argument, on purpose.** No account, session, IP address, user agent, order or
 * request id can reach the counter, because the port has nowhere to put one. A counter that needs to be
 * split — by surface, or by time window — is split by the application wiring one counter per surface, and
 * by the adapter bucketing increments into windows; neither split is about a person.
 *
 * Only robot verdicts are counted, by choice, and not the friction steps they add. A missing or malformed
 * verdict is not a robot verdict and is not counted, even when the policy adds a step for it: a detector that
 * goes silent then shows as a count that drops, instead of being masked by verdicts that never arrived. The
 * steps added, when they are wanted, are counted by the limiter that absorbs them.
 *
 * The adapter belongs to the application: an in-memory or cache counter flushed per window, a metrics
 * gauge read by an alerting dashboard, or a client that pushes one number per window to another service.
 */
interface RobotVerdictCounter
{
    public function increment(): void;
}
