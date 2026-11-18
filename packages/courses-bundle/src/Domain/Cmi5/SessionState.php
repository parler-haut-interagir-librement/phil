<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Cmi5;

/**
 * Where a cmi5 launch session stands: launched by the platform, initialized by the assignable unit, then terminated by
 * it or abandoned when the learner launched again without it terminating.
 */
enum SessionState: string
{
    case Launched = 'launched';
    case Initialized = 'initialized';
    case Terminated = 'terminated';
    case Abandoned = 'abandoned';

    public function isOpen(): bool
    {
        return self::Launched === $this || self::Initialized === $this;
    }
}
