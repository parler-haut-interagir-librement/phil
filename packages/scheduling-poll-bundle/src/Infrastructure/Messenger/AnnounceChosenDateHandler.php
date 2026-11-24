<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Messenger;

use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Application\Message\SendChosenDate;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues one `SendChosenDate` per respondent of a poll closed on a date who answered and was not revoked. Handled
 * twice, it queues them twice, and each is claimed once: no respondent gets the date twice.
 */
final readonly class AnnounceChosenDateHandler
{
    public function __construct(
        private SchedulingPollRepository $polls,
        private RespondentRepository $respondents,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AnnounceChosenDate $message): void
    {
        $poll = $this->polls->get($message->poll);

        if (!$poll instanceof SchedulingPoll || null === $poll->chosenSlot()) {
            return;
        }

        foreach ($this->respondents->ofPoll($poll->id) as $respondent) {
            if ($respondent->hasAnswered() && !$respondent->isRevoked()) {
                $this->bus->dispatch(new SendChosenDate($poll->id, $respondent->id));
            }
        }
    }
}
