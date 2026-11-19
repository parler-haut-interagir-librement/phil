<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\ReceptionSettings;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\Repository\ReceptionRepository;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The conversations' part of an access or erasure request, the data subject being the participant's identifier. The
 * export gives the conversations the person took part in, the messages they wrote, opened, and their reception
 * settings. The erasure wipes the body and author of their messages — the others keep the conversation, a message
 * shown as erased —, removes their participations and settings, their identifier from the blocks of others, and the
 * conversations left without anyone. A data subject that cannot be a participant holds nothing here.
 */
final readonly class ConversationPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'conversations';

    public function __construct(
        private ConversationRepository $conversations,
        private ReceptionRepository $reception,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $participant = $this->participant($subject);

        if (!$participant instanceof ParticipantId) {
            return [];
        }

        $conversations = $this->conversations->conversationsOf($participant);
        $messages = $this->conversations->messagesBy($participant);
        $settings = $this->reception->of($participant);

        if ([] === $conversations && [] === $messages && $this->isDefault($settings)) {
            return [];
        }

        return [
            'conversations' => array_map($this->conversation(...), $conversations),
            'messages' => array_map($this->message(...), $messages),
            'reception' => [
                'who_can_write' => $settings->whoCanWrite()->value,
                'blocked' => array_map(static fn (ParticipantId $other): string => $other->value, $settings->blocked()),
            ],
        ];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $participant = $this->participant($subject);

        if ($participant instanceof ParticipantId) {
            $this->conversations->forget($participant);
            $this->reception->forget($participant);
        }

        return ErasureReport::of(self::MODULE, [
            ConversationTables::MESSAGE => ErasureEffect::Redacted,
            ConversationTables::PARTICIPANT => ErasureEffect::Deleted,
            ConversationTables::RECEPTION => ErasureEffect::Deleted,
            ConversationTables::BLOCK => ErasureEffect::Deleted,
        ]);
    }

    private function participant(DataSubject $subject): ?ParticipantId
    {
        try {
            return ParticipantId::fromString($subject->value);
        } catch (InvalidParticipant) {
            return null;
        }
    }

    private function isDefault(ReceptionSettings $settings): bool
    {
        return WhoCanWrite::Everyone === $settings->whoCanWrite() && [] === $settings->blocked();
    }

    /**
     * @return array<string, mixed>
     */
    private function conversation(InboxEntry $entry): array
    {
        return [
            'id' => $entry->conversation->id->value,
            'kind' => $entry->conversation->kind->value,
            'subject' => $entry->conversation->subject(),
            'joined_at' => $this->moment($entry->participation->joinedAt),
            'left_at' => $this->moment($entry->participation->leftAt()),
            'muted' => $entry->participation->isMuted(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(Message $message): array
    {
        return [
            'conversation' => $message->conversation->value,
            'id' => $message->id->value,
            'sent_at' => $this->moment($message->sentAt),
            'body' => $message->body,
        ];
    }

    private function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(DateTimeInterface::ATOM);
    }
}
