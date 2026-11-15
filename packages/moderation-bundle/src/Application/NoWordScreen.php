<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Override;
use Phil\ModerationBundle\Application\Contract\WordScreen;

/**
 * Lets every text through: the default until the application plugs the watched words in.
 */
final readonly class NoWordScreen implements WordScreen
{
    #[Override]
    public function screen(string $text): Screening
    {
        return Screening::allow($text);
    }
}
