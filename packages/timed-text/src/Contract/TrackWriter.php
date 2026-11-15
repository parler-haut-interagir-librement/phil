<?php

declare(strict_types=1);

namespace Phil\TimedText\Contract;

use Phil\TimedText\Track;

/**
 * Writes a track in one format. A writer keeps no state between calls, and leaves out what its format cannot carry.
 */
interface TrackWriter
{
    public function write(Track $track): string;
}
