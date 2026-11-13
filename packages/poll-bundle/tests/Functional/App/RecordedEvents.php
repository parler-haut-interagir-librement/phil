<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional\App;

use Phil\PollBundle\Application\Event\VoteCast;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The polls whose ballots the application heard of.
 */
final class RecordedEvents
{
    /** @var list<string> */
    public array $polls = [];

    #[AsEventListener]
    public function onVoteCast(VoteCast $event): void
    {
        $this->polls[] = $event->poll;
    }
}
