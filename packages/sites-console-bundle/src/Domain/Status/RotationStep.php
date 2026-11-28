<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * Where one step of a rotation stands for one site: not tried yet, done, failed, or skipped when the site left the
 * console during the rotation.
 */
enum RotationStep: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * Whether the rotation may go past this step.
     */
    public function isSettled(): bool
    {
        return self::Done === $this || self::Skipped === $this;
    }
}
