<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Notifications;

use Override;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Domain\Exception\ConversationException;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Says what a notified conversation stands for when the notification is shown: its subject, or the names of its
 * participants, and the page of the conversation when the bundle's routes are imported. A conversation erased since
 * has no label.
 */
final readonly class ConversationSubjects implements SubjectResolver
{
    public function __construct(
        private ConversationReader $reader,
        private ParticipantDirectory $directory,
        private ?UrlGeneratorInterface $urls = null,
    ) {
    }

    #[Override]
    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        if (NotifyParticipants::SUBJECT !== $subject->type) {
            return null;
        }

        try {
            $id = ConversationId::fromString($subject->id);
            $conversation = $this->reader->conversation($id);
        } catch (ConversationException) {
            return null;
        }

        if (!$conversation instanceof Conversation) {
            return null;
        }

        return new ResolvedSubject($conversation->subject() ?? $this->names($id), $this->url($id));
    }

    private function names(ConversationId $id): string
    {
        $active = array_filter($this->reader->participants($id), static fn (Participation $participation): bool => $participation->isActive());

        return implode(', ', array_map(fn (Participation $participation): string => $this->directory->displayName($participation->participant), $active));
    }

    private function url(ConversationId $id): ?string
    {
        try {
            return $this->urls?->generate(ConversationController::THREAD_ROUTE, ['id' => $id->value], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RoutingException) {
            return null;
        }
    }
}
