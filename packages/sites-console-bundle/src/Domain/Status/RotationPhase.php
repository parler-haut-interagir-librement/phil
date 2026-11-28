<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * Where a rotation of the console key stands: the next key is being staged on the sites, the console signs with the
 * next key while the previous one is being retired, or the rotation is finished.
 */
enum RotationPhase: string
{
    case Staging = 'staging';
    case Retiring = 'retiring';
    case Finished = 'finished';

    /**
     * Whether the console signs with the next key in this phase.
     */
    public function signsWithNext(): bool
    {
        return self::Staging !== $this;
    }
}
