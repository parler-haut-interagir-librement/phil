<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Http;

use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Domain\Exception\ConversationException;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Domain\Exception\HelpdeskException;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Exception\TransitionRefused;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of the requesters, for the participant signed in: their requests, the form that opens one in a queue, and a
 * request with its conversation, the form that answers and the one that closes it. Every change is posted with a CSRF
 * token; a request of someone else is not found. Without a participant, the pages meet the firewall.
 */
final readonly class RequesterController
{
    public const string REQUESTS_ROUTE = 'phil_helpdesk_requests';

    public const string NEW_ROUTE = 'phil_helpdesk_new';

    public const string REQUEST_ROUTE = 'phil_helpdesk_request';

    public const string ANSWER_ROUTE = 'phil_helpdesk_answer';

    public const string WITHDRAW_ROUTE = 'phil_helpdesk_withdraw';

    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private Helpdesk $helpdesk,
        private Conversations $conversations,
        private Queues $queues,
        private CurrentParticipant $current,
        private HelpdeskRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
    ) {
    }

    public function requests(Request $request): Response
    {
        return $this->render('@PhilHelpdesk/requests.html.twig', [
            'tickets' => $this->helpdesk->requests($this->participant(), max(1, $request->query->getInt('page', 1))),
            'page' => max(1, $request->query->getInt('page', 1)),
        ]);
    }

    public function new(Request $request): Response
    {
        $requester = $this->participant();
        $error = null;

        if ($request->isMethod('POST')) {
            $this->trust($request);

            try {
                $ticket = $this->helpdesk->open(
                    $requester,
                    QueueName::fromString($request->request->getString('queue')),
                    $request->request->getString('subject'),
                    $request->request->getString('body'),
                );

                return new RedirectResponse($this->urls->generate(self::REQUEST_ROUTE, ['id' => $ticket->conversation->value]), Response::HTTP_SEE_OTHER);
            } catch (InvalidQueue|InvalidConversation $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('@PhilHelpdesk/new.html.twig', [
            'queues' => $this->queues->all(),
            'error' => $error,
            'values' => ['queue' => $request->request->getString('queue'), 'subject' => $request->request->getString('subject'), 'body' => $request->request->getString('body')],
        ], null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function show(string $id): Response
    {
        $requester = $this->participant();
        $ticket = $this->ticket($id, $requester);
        $this->conversations->markRead($ticket->conversation, $requester);

        return $this->page($ticket, $requester, null);
    }

    public function answer(Request $request, string $id): Response
    {
        $requester = $this->participant();
        $ticket = $this->ticket($id, $requester);
        $this->trust($request);

        try {
            $this->helpdesk->answer($ticket->conversation, $requester, $request->request->getString('body'));
        } catch (InvalidConversation|TransitionRefused $exception) {
            return $this->page($ticket, $requester, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->urls->generate(self::REQUEST_ROUTE, ['id' => $ticket->conversation->value]), Response::HTTP_SEE_OTHER);
    }

    public function withdraw(Request $request, string $id): Response
    {
        $requester = $this->participant();
        $ticket = $this->ticket($id, $requester);
        $this->trust($request);

        try {
            $this->helpdesk->withdraw($ticket->conversation, $requester);
        } catch (TransitionRefused $transitionRefused) {
            return $this->page($ticket, $requester, $transitionRefused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->urls->generate(self::REQUEST_ROUTE, ['id' => $ticket->conversation->value]), Response::HTTP_SEE_OTHER);
    }

    private function page(Ticket $ticket, ParticipantId $requester, ?string $error, int $status = Response::HTTP_OK): Response
    {
        return $this->render('@PhilHelpdesk/request.html.twig', [
            'ticket' => $ticket,
            'conversation' => $this->conversations->open($ticket->conversation, $requester),
            'messages' => $this->conversations->thread($ticket->conversation, $requester),
            'me' => $requester,
            'queue' => $this->queues->get($ticket->queue()),
            'error' => $error,
        ], $status);
    }

    private function ticket(string $id, ParticipantId $requester): Ticket
    {
        try {
            return $this->helpdesk->ofRequester(ConversationId::fromString($id), $requester);
        } catch (ConversationException|HelpdeskException $exception) {
            throw new NotFoundHttpException('No such request.', $exception);
        }
    }

    private function participant(): ParticipantId
    {
        return $this->current->current() ?? throw new AccessDeniedException('Sign in to read your requests.');
    }

    private function trust(Request $request): void
    {
        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }
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
