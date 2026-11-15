<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application\Contract;

/**
 * Whether the current viewer agreed that their viewings are counted: the application's consent manager answers. Without
 * it, nothing is counted and the player sends nothing.
 */
interface ViewingConsent
{
    public function granted(): bool;
}
