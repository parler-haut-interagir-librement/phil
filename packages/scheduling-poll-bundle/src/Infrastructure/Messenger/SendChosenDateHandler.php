<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Messenger;

use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Contract\RespondentAddresses;
use Phil\SchedulingPollBundle\Application\Message\SendChosenDate;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Infrastructure\Mailer\ChosenDateMail;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Sends the date chosen to one respondent, with its iCalendar file: reads the address — the one they gave, else the
 * one the application knows for their account —, claims the notice in one conditional write, then builds and sends
 * the mail. Without the Mailer, nothing is sent. A respondent without an address, who has not answered, revoked, or already sent the notice is skipped. A mail that fails gives
 * the notice back before the exception goes on, so that Messenger tries the message again and the respondent gets it
 * once.
 */
final readonly class SendChosenDateHandler
{
    public function __construct(
        private SchedulingPollRepository $polls,
        private RespondentRepository $respondents,
        private RespondentAddresses $addresses,
        private ChosenDateMail $mail,
        private ClockInterface $clock,
        private ?MailerInterface $mailer = null,
    ) {
    }

    public function __invoke(SendChosenDate $message): void
    {
        $respondent = $this->respondents->get($message->respondent);
        $poll = $this->polls->get($message->poll);

        if (!$respondent instanceof Respondent || !$poll instanceof SchedulingPoll || $respondent->poll !== $poll->id || $respondent->isRevoked() || !$respondent->hasAnswered()) {
            return;
        }

        $slot = array_find($this->polls->slots($poll->id), static fn (Slot $slot): bool => $slot->id === $poll->chosenSlot());
        $address = $this->address($respondent);
        $now = $this->clock->now();

        if (!$this->mailer instanceof MailerInterface || !$slot instanceof Slot || !$address instanceof Address || !$this->respondents->claimNotice($respondent->id, $now)) {
            return;
        }

        try {
            $this->mailer->send($this->mail->build($poll, $slot, $respondent, $address, $now));
        } catch (Throwable $throwable) {
            $this->respondents->releaseNotice($respondent->id);

            throw $throwable;
        }
    }

    private function address(Respondent $respondent): ?Address
    {
        $email = $respondent->email();

        if (null !== $email) {
            return new Address($email, (string) $respondent->name());
        }

        $account = $respondent->account();

        return $account instanceof ResourceReference ? $this->addresses->addressOf($account) : null;
    }
}
