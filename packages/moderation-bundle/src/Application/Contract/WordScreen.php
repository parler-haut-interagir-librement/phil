<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Application\Screening;

/**
 * The watched words: reads a text and says whether it passes, is flagged, held or blocked, with the text to keep when a
 * word is replaced or censored.
 */
interface WordScreen
{
    public function screen(string $text): Screening;
}
