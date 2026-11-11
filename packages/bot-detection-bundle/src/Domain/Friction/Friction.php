<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Domain\Friction;

/**
 * What a verdict does to the request it decides. There is deliberately no `Refuse` case: a verdict is
 * forgeable and a false positive must still get through, so the strongest outcome is one more step of
 * friction.
 *
 * The application gives `Step` its meaning on each surface: one more step of back-off on a sign-up limiter,
 * a forced stronger authentication challenge or one more step on a payment attempt limiter, or a hit that is
 * not counted in audience measurement. **The step never records why it was added**: it melts into the state
 * of whatever it slows down, and that state does not keep the verdict.
 */
enum Friction
{
    case None;
    case Step;

    public function adds(): bool
    {
        return self::Step === $this;
    }
}
