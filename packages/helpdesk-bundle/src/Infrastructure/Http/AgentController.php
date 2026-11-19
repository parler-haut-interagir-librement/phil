<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Http;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Domain\Exception\ConversationException;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Application\TicketWorkflow;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Exception\HelpdeskException;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Exception\TicketNotFound;
use Phil\HelpdeskBundle\Domain\Exception\TransitionRefused;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use Phil\HelpdeskBundle\Infrastructure\EasyAdmin\HelpdeskAdmin;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of the agents: the desk, the requests of the queues they work, filtered by queue, status and assignee; and
 * a request with its conversation, the forms that answer — in their own words or with a canned reply —, assign it,
 * apply a transition and move it to another queue. Every change is posted with a CSRF token; a request of a queue the
 * agent does not work is not found, and someone who works no queue meets the firewall.
 *
 * The pages answer on the bundle's routes, which the application imports, and, when EasyAdminBundle is registered, on
 * the routes `#[AdminRoute]` grafts on each dashboard: there they render in `HelpdeskAdmin::LAYOUT`, and their links and
 * the redirection after a form go to the routes of the same dashboard.
 */
#[AdminRoute(path: '/helpdesk', name: 'helpdesk')]
final readonly class AgentController
{
    public const string DESK_ROUTE = 'phil_helpdesk_desk';

    public const string TICKET_ROUTE = 'phil_helpdesk_ticket';

    public const string REPLY_ROUTE = 'phil_helpdesk_reply';

    public const string ASSIGN_ROUTE = 'phil_helpdesk_assign';

    public const string TRANSITION_ROUTE = 'phil_helpdesk_transition';

    public const string MOVE_ROUTE = 'phil_helpdesk_move';

    public const string UUID = RequesterController::UUID;

    public const string TRANSITION = 'take|resolve|reopen|close';

    public function __construct(
        private Helpdesk $helpdesk,
        private ConversationReader $reader,
        private Queues $queues,
        private CurrentParticipant $current,
        private HelpdeskRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
    ) {
    }

    #[AdminRoute(path: '/', name: 'desk', options: ['methods' => ['GET']])]
    public function desk(Request $request): Response
    {
        $agent = $this->agent();
        $queue = $this->queueFilter($request->query->getString('queue'));
        $status = Status::tryFrom($request->query->getString('status'));
        $mine = $request->query->getBoolean('mine');
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render($request, '@PhilHelpdesk/desk.html.twig', [
            'tickets' => $this->helpdesk->desk($queue, $status, $mine ? $agent : null, $page),
            'queues' => $this->helpdesk->queues(),
            'statuses' => Status::cases(),
            'filters' => ['queue' => $queue?->value, 'status' => $status?->value, 'mine' => $mine],
            'page' => $page,
        ]);
    }

    #[AdminRoute(path: '/{id}', name: 'ticket', options: ['requirements' => ['id' => self::UUID], 'methods' => ['GET']])]
    public function show(Request $request, string $id): Response
    {
        return $this->page($request, $this->ticket($id), $this->agent(), null);
    }

    #[AdminRoute(path: '/{id}/reply', name: 'reply', options: ['requirements' => ['id' => self::UUID], 'methods' => ['POST']])]
    public function reply(Request $request, string $id): Response
    {
        $agent = $this->agent();
        $ticket = $this->ticket($id);
        $this->trust($request);
        $canned = $request->request->getString('canned_reply');

        try {
            '' === $canned
                ? $this->helpdesk->reply($ticket->conversation, $agent, $request->request->getString('body'))
                : $this->helpdesk->replyWith($ticket->conversation, $agent, $canned, $request->getLocale());
        } catch (InvalidConversation|CannedReplyNotFound|TransitionRefused $exception) {
            return $this->page($request, $ticket, $agent, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($request, $ticket);
    }

    #[AdminRoute(path: '/{id}/assign', name: 'assign', options: ['requirements' => ['id' => self::UUID], 'methods' => ['POST']])]
    public function assign(Request $request, string $id): RedirectResponse
    {
        $agent = $this->agent();
        $ticket = $this->ticket($id);
        $this->trust($request);
        $this->helpdesk->assign($ticket->conversation, $request->request->getBoolean('unassign') ? null : $agent, $agent);

        return $this->back($request, $ticket);
    }

    #[AdminRoute(path: '/{id}/transition/{transition}', name: 'transition', options: ['requirements' => ['id' => self::UUID, 'transition' => self::TRANSITION], 'methods' => ['POST']])]
    public function transition(Request $request, string $id, string $transition): Response
    {
        $agent = $this->agent();
        $ticket = $this->ticket($id);
        $this->trust($request);

        try {
            $this->helpdesk->apply($ticket->conversation, $transition);
        } catch (TransitionRefused $transitionRefused) {
            return $this->page($request, $ticket, $agent, $transitionRefused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($request, $ticket);
    }

    #[AdminRoute(path: '/{id}/move', name: 'move', options: ['requirements' => ['id' => self::UUID], 'methods' => ['POST']])]
    public function move(Request $request, string $id): Response
    {
        $agent = $this->agent();
        $ticket = $this->ticket($id);
        $this->trust($request);

        try {
            $this->helpdesk->move($ticket->conversation, QueueName::fromString($request->request->getString('queue')));
        } catch (InvalidQueue|TicketNotFound $exception) {
            return $this->page($request, $ticket, $agent, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($request, $ticket);
    }

    private function page(Request $request, Ticket $ticket, ParticipantId $agent, ?string $error, int $status = Response::HTTP_OK): Response
    {
        $ticket = $this->helpdesk->forAgent($ticket->conversation);

        return $this->render($request, '@PhilHelpdesk/ticket.html.twig', [
            'ticket' => $ticket,
            'conversation' => $this->reader->conversation($ticket->conversation),
            'messages' => $this->reader->messages($ticket->conversation, Helpdesk::PAGE_SIZE),
            'me' => $agent,
            'queue' => $this->queues->get($ticket->queue()),
            'queues' => $this->helpdesk->queues(),
            'canned_replies' => $this->helpdesk->cannedReplies($ticket),
            'transitions' => $this->helpdesk->transitions($ticket),
            'error' => $error,
        ], $status);
    }

    private function ticket(string $id): Ticket
    {
        $this->agent();

        try {
            return $this->helpdesk->forAgent(ConversationId::fromString($id));
        } catch (ConversationException|HelpdeskException $exception) {
            throw new NotFoundHttpException('No such request.', $exception);
        }
    }

    /**
     * The participant signed in, who works at least one queue.
     */
    private function agent(): ParticipantId
    {
        $agent = $this->current->current();

        if (!$agent instanceof ParticipantId || [] === $this->helpdesk->queues()) {
            throw new AccessDeniedException('Only the agents of a queue use the help desk.');
        }

        return $agent;
    }

    private function queueFilter(string $queue): ?QueueName
    {
        try {
            return '' === $queue ? null : QueueName::fromString($queue);
        } catch (InvalidQueue) {
            return null;
        }
    }

    private function trust(Request $request): void
    {
        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }
    }

    private function back(Request $request, Ticket $ticket): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($this->routes($request)['ticket'], ['id' => $ticket->conversation->value]), Response::HTTP_SEE_OTHER);
    }

    /**
     * The routes of the pages, by their part: the bundle's, or those of the dashboard the request came through.
     *
     * @return array{desk: string, ticket: string, reply: string, assign: string, transition: string, move: string}
     */
    private function routes(Request $request): array
    {
        $dashboard = HelpdeskAdmin::dashboardRoute($request);
        $routes = [
            'desk' => self::DESK_ROUTE,
            'ticket' => self::TICKET_ROUTE,
            'reply' => self::REPLY_ROUTE,
            'assign' => self::ASSIGN_ROUTE,
            'transition' => self::TRANSITION_ROUTE,
            'move' => self::MOVE_ROUTE,
        ];

        if (null === $dashboard) {
            return $routes;
        }

        return array_map(static fn (string $route): string => $dashboard.HelpdeskAdmin::ROUTES[$route], $routes);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(Request $request, string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => null === HelpdeskAdmin::dashboardRoute($request) ? $this->layout : HelpdeskAdmin::LAYOUT,
            'routes' => $this->routes($request),
            'token' => $this->request->token(),
            'manual' => TicketWorkflow::MANUAL,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
