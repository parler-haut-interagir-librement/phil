<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application;

use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Model\ReceptionSettings;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\Repository\ReceptionRepository;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The reception settings of the participants, and what they allow: a block, either way, stops a conversation from
 * starting and a direct one from going on; otherwise whom the recipient lets start a conversation with them decides.
 */
final readonly class Reception
{
    public function __construct(
        private ReceptionRepository $settings,
        private ContactRelation $contacts = new NoContacts(),
    ) {
    }

    public function settings(ParticipantId $participant): ReceptionSettings
    {
        return $this->settings->of($participant);
    }

    public function allow(ParticipantId $participant, WhoCanWrite $whoCanWrite): void
    {
        $settings = $this->settings->of($participant);
        $settings->allow($whoCanWrite);

        $this->settings->save($settings);
    }

    /**
     * @throws InvalidParticipant when blocking oneself, or one more person past the limit
     */
    public function block(ParticipantId $participant, ParticipantId $other): void
    {
        $settings = $this->settings->of($participant);
        $settings->block($other);

        $this->settings->save($settings);
    }

    public function unblock(ParticipantId $participant, ParticipantId $other): void
    {
        $settings = $this->settings->of($participant);
        $settings->unblock($other);

        $this->settings->save($settings);
    }

    /**
     * Whether one of the two blocked the other.
     */
    public function blockedEitherWay(ParticipantId $one, ParticipantId $other): bool
    {
        return $this->settings->of($one)->hasBlocked($other) || $this->settings->of($other)->hasBlocked($one);
    }

    /**
     * Whether `$from` may start a conversation with `$to`, or bring them into a group.
     */
    public function mayStart(ParticipantId $from, ParticipantId $to): bool
    {
        $recipient = $this->settings->of($to);

        if ($recipient->hasBlocked($from) || $this->settings->of($from)->hasBlocked($to)) {
            return false;
        }

        return match ($recipient->whoCanWrite()) {
            WhoCanWrite::Everyone => true,
            WhoCanWrite::Contacts => $this->contacts->areContacts($to, $from),
            WhoCanWrite::Nobody => false,
        };
    }
}
