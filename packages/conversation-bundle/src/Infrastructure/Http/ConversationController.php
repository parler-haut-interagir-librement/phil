<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Http;

use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Exception\ConversationException;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Exception\NotAParticipant;
use Phil\ConversationBundle\Domain\Exception\WritingRefused;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of the conversations, for the participant signed in: the inbox, a conversation with its reply form, the
 * form that writes to a member, and the reception settings. Every change is posted with a CSRF token; a conversation
 * the participant does not take part in is not found. Without a participant, the pages meet the firewall.
 */
final readonly class ConversationController
{
    public const string INBOX_ROUTE = 'phil_conversation_inbox';

    public const string START_ROUTE = 'phil_conversation_start';

    public const string THREAD_ROUTE = 'phil_conversation_thread';

    public const string MESSAGES_ROUTE = 'phil_conversation_messages';

    public const string REPLY_ROUTE = 'phil_conversation_reply';

    public const string MUTE_ROUTE = 'phil_conversation_mute';

    public const string LEAVE_ROUTE = 'phil_conversation_leave';

    public const string SETTINGS_ROUTE = 'phil_conversation_settings';

    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /**
     * @param ?string $topicPrefix the prefix of the participants' Mercure topics, null without real time
     */
    public function __construct(
        private Conversations $conversations,
        private Reception $reception,
        private CurrentParticipant $current,
        private ConversationRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private ?string $topicPrefix = null,
    ) {
    }

    public function inbox(Request $request): Response
    {
        $participant = $this->participant();
        $before = $request->query->has('before') ? $request->query->getInt('before') : null;

        return $this->render('@PhilConversation/inbox.html.twig', [
            'entries' => $this->conversations->inbox($participant, null, $before),
            'unread' => $this->conversations->unreadConversations($participant),
        ]);
    }

    public function start(Request $request, string $participant): Response
    {
        $from = $this->participant();
        $to = $this->other($participant);
        $error = null;

        if ($request->isMethod('POST')) {
            $this->trust($request);

            try {
                $conversation = $this->conversations->startDirect($from, $to, $request->request->getString('body'));

                return new RedirectResponse($this->urls->generate(self::THREAD_ROUTE, ['id' => $conversation->id->value]), Response::HTTP_SEE_OTHER);
            } catch (InvalidConversation|WritingRefused $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('@PhilConversation/start.html.twig', ['to' => $to, 'error' => $error, 'body' => $request->request->getString('body')], null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function thread(Request $request, string $id): Response
    {
        $participant = $this->participant();
        $conversationId = $this->id($id);
        $before = $request->query->has('before') ? $request->query->getInt('before') : null;

        try {
            $conversation = $this->conversations->open($conversationId, $participant);
            $messages = $this->conversations->thread($conversationId, $participant, null, $before);

            if (null === $before) {
                $this->conversations->markRead($conversationId, $participant);
            }
        } catch (NotAParticipant $notaParticipant) {
            throw new NotFoundHttpException($notaParticipant->getMessage(), $notaParticipant);
        }

        return $this->render('@PhilConversation/thread.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'participations' => $this->conversations->participants($conversationId),
            'me' => $participant,
            'error' => null,
            'topic' => null === $this->topicPrefix ? null : $this->topicPrefix.$participant->value,
        ]);
    }

    /**
     * The latest messages alone, which the page loads again when a new one comes.
     */
    public function messages(string $id): Response
    {
        $participant = $this->participant();
        $conversationId = $this->id($id);

        try {
            $messages = $this->conversations->thread($conversationId, $participant);
            $this->conversations->markRead($conversationId, $participant);
        } catch (NotAParticipant $notaParticipant) {
            throw new NotFoundHttpException($notaParticipant->getMessage(), $notaParticipant);
        }

        return $this->render('@PhilConversation/_messages.html.twig', ['messages' => $messages, 'me' => $participant]);
    }

    public function reply(Request $request, string $id): Response
    {
        $participant = $this->participant();
        $conversationId = $this->id($id);
        $this->trust($request);

        try {
            $this->conversations->reply($conversationId, $participant, $request->request->getString('body'));
        } catch (NotAParticipant $notAParticipant) {
            throw new NotFoundHttpException($notAParticipant->getMessage(), $notAParticipant);
        } catch (InvalidConversation|WritingRefused $exception) {
            return $this->render('@PhilConversation/thread.html.twig', [
                'conversation' => $this->conversations->open($conversationId, $participant),
                'messages' => $this->conversations->thread($conversationId, $participant),
                'participations' => $this->conversations->participants($conversationId),
                'me' => $participant,
                'error' => $exception->getMessage(),
                'topic' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($conversationId);
    }

    public function mute(Request $request, string $id): RedirectResponse
    {
        $participant = $this->participant();
        $conversationId = $this->id($id);
        $this->trust($request);

        try {
            $this->conversations->mute($conversationId, $participant, $request->request->getBoolean('muted'));
        } catch (NotAParticipant $notaParticipant) {
            throw new NotFoundHttpException($notaParticipant->getMessage(), $notaParticipant);
        }

        return $this->back($conversationId);
    }

    public function leave(Request $request, string $id): RedirectResponse
    {
        $participant = $this->participant();
        $conversationId = $this->id($id);
        $this->trust($request);

        try {
            $this->conversations->leave($conversationId, $participant);
        } catch (NotAParticipant $notaParticipant) {
            throw new NotFoundHttpException($notaParticipant->getMessage(), $notaParticipant);
        }

        return new RedirectResponse($this->urls->generate(self::INBOX_ROUTE), Response::HTTP_SEE_OTHER);
    }

    /**
     * The reception settings, and the forms that change whom the participant lets write to them, and whom they block.
     */
    public function settings(Request $request): Response
    {
        $participant = $this->participant();
        $error = null;

        if ($request->isMethod('POST')) {
            $this->trust($request);

            try {
                match ($request->request->getString('action')) {
                    'allow' => $this->reception->allow($participant, WhoCanWrite::tryFrom($request->request->getString('who_can_write')) ?? throw new BadRequestHttpException('No such setting.')),
                    'block' => $this->reception->block($participant, ParticipantId::fromString(trim($request->request->getString('participant')))),
                    'unblock' => $this->reception->unblock($participant, ParticipantId::fromString($request->request->getString('participant'))),
                    default => throw new BadRequestHttpException('No such action.'),
                };

                return new RedirectResponse($this->urls->generate(self::SETTINGS_ROUTE), Response::HTTP_SEE_OTHER);
            } catch (InvalidParticipant $invalidParticipant) {
                $error = $invalidParticipant->getMessage();
            }
        }

        return $this->render('@PhilConversation/settings.html.twig', [
            'settings' => $this->reception->settings($participant),
            'choices' => WhoCanWrite::cases(),
            'error' => $error,
        ], null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function participant(): ParticipantId
    {
        return $this->current->current() ?? throw new AccessDeniedException('Sign in to read your conversations.');
    }

    private function other(string $participant): ParticipantId
    {
        try {
            return ParticipantId::fromString($participant);
        } catch (ConversationException $conversationException) {
            throw new NotFoundHttpException('No such participant.', $conversationException);
        }
    }

    private function id(string $id): ConversationId
    {
        try {
            return ConversationId::fromString($id);
        } catch (ConversationException $conversationException) {
            throw new NotFoundHttpException('No such conversation.', $conversationException);
        }
    }

    private function trust(Request $request): void
    {
        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }
    }

    private function back(ConversationId $id): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::THREAD_ROUTE, ['id' => $id->value]), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => $this->layout,
            'token' => $this->request->token(),
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
