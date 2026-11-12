<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Support;

use Phil\SocialCardsBundle\Application\Contract\CardQueue;

/**
 * A queue that keeps the fingerprints it was given.
 */
final class RecordingQueue implements CardQueue
{
    /** @var list<string> */
    public array $queued = [];

    public function queue(string $fingerprint): void
    {
        $this->queued[] = $fingerprint;
    }
}
