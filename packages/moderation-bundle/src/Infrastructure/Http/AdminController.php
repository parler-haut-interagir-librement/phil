<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Http;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Application\Exception\UnknownTarget;
use Phil\ModerationBundle\Application\Grounds;
use Phil\ModerationBundle\Application\MemberNotes;
use Phil\ModerationBundle\Application\ModerationQueue;
use Phil\ModerationBundle\Application\Moderator;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentProposal;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The pages of the moderation, for moderators only, every one through the bundle's services: the single queue, a page
 * per target with its reports, its history and the decision form — with the grounds a statement of reasons tells the
 * author when the decision restricts them —, the page of a member with the decisions on their
 * content and the notes of the moderation. When `phil/incident-mode-bundle` is registered with its administration, the
 * pages link to its page of the incidents for the role that runs it, and the queue proposes an incident when reports
 * surge. The forms are posted with a CSRF token. A member who is no moderator meets the firewall,
 * as with any denied page.
 *
 * The pages answer on the bundle's routes, which the application imports, and, when EasyAdminBundle is registered, on
 * the routes `#[AdminRoute]` grafts on each dashboard: there they render in `ModerationAdmin::LAYOUT`, and their links
 * and redirections go to the routes of the same dashboard.
 */
#[AdminRoute(path: '/moderation', name: 'moderation')]
final readonly class AdminController
{
    public const string QUEUE_ROUTE = 'phil_moderation_queue';

    public const string ENTRY_ROUTE = 'phil_moderation_entry';

    public const string DECIDE_ROUTE = 'phil_moderation_decide';

    public const string MEMBER_ROUTE = 'phil_moderation_member';

    public const string NOTE_ROUTE = 'phil_moderation_note';

    public const string NOTE_REMOVE_ROUTE = 'phil_moderation_note_remove';

    public const string CSRF_TOKEN_ID = 'phil_moderation_admin';

    public const string HEX = '[0-9a-f]{32}';

    /**
     * @param positive-int $pageSize
     */
    public function __construct(
        private ModerationQueue $queue,
        private Decisions $decisions,
        private MemberNotes $notes,
        private CurrentModerator $currentModerator,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private int $pageSize,
        private ?IncidentProposal $incidents = null,
        private bool $statements = false,
    ) {
    }

    #[AdminRoute(path: '/', name: 'queue', options: ['methods' => ['GET']])]
    public function queue(Request $request): Response
    {
        $moderator = $this->moderator();
        $page = max(1, $request->query->getInt('page'));
        $total = $this->queue->count();
        $items = $this->queue->items($this->pageSize, ($page - 1) * $this->pageSize);
        $views = [];

        foreach ($items as $index => $item) {
            $views[$index] = $this->queue->describe($item->target);
        }

        return $this->render($request, '@PhilModeration/admin/queue.html.twig', [
            'items' => $items,
            'views' => $views,
            'page' => $page,
            'pages' => max(1, intdiv($total + $this->pageSize - 1, $this->pageSize)),
            'total' => $total,
            'moderator' => $moderator,
            'incident_proposed' => true === $this->incidents?->isProposed(),
        ]);
    }

    #[AdminRoute(path: '/entry', name: 'entry', options: ['methods' => ['GET']])]
    public function entry(Request $request): Response
    {
        $this->moderator();
        $target = $this->target($request->query->getString('type'), $request->query->getString('id'));

        return $this->render($request, '@PhilModeration/admin/entry.html.twig', [
            'entry' => $this->queue->entry($target),
            'actions' => DecisionAction::cases(),
            'may_decide' => $this->decisions->mayDecide($target->type),
            'error' => null,
        ]);
    }

    #[AdminRoute(path: '/entry', name: 'decide', options: ['methods' => ['POST']])]
    public function decide(Request $request): Response
    {
        $this->moderator();
        $this->checkToken($request);
        $target = $this->target($request->request->getString('type'), $request->request->getString('id'));
        $action = DecisionAction::tryFrom($request->request->getString('action')) ?? throw new BadRequestHttpException('No such action.');

        try {
            $this->decisions->decide(
                $target,
                $action,
                $request->request->getString('reason'),
                $request->request->getString('note'),
                $request->request->getBoolean('public'),
                $this->grounds($request),
            );
        } catch (ModerationRefused $refused) {
            throw $this->denied($refused->getMessage());
        } catch (UnknownTarget) {
            throw new NotFoundHttpException('No such target.');
        } catch (InvalidModeration $invalid) {
            return $this->render($request, '@PhilModeration/admin/entry.html.twig', [
                'entry' => $this->queue->entry($target),
                'actions' => DecisionAction::cases(),
                'may_decide' => true,
                'error' => $invalid->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirect($request, 'queue');
    }

    #[AdminRoute(path: '/member', name: 'member', options: ['methods' => ['GET']])]
    public function member(Request $request): Response
    {
        $this->moderator();
        $member = $this->memberId($request->query->getString('member'));

        return $this->render($request, '@PhilModeration/admin/member.html.twig', [
            'member' => $member,
            'decisions' => $this->decisions->about($member),
            'notes' => $this->notes->about($member),
            'error' => null,
        ]);
    }

    #[AdminRoute(path: '/member/notes', name: 'note', options: ['methods' => ['POST']])]
    public function note(Request $request): Response
    {
        $this->moderator();
        $this->checkToken($request);
        $member = $this->memberId($request->request->getString('member'));

        try {
            $this->notes->write($member, $request->request->getString('body'));
        } catch (InvalidModeration $invalidModeration) {
            return $this->render($request, '@PhilModeration/admin/member.html.twig', [
                'member' => $member,
                'decisions' => $this->decisions->about($member),
                'notes' => $this->notes->about($member),
                'error' => $invalidModeration->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirect($request, 'member', ['member' => $member->value]);
    }

    #[AdminRoute(path: '/member/notes/{id}/remove', name: 'note_remove', options: ['requirements' => ['id' => self::HEX], 'methods' => ['POST']])]
    public function removeNote(Request $request, string $id): RedirectResponse
    {
        $this->moderator();
        $this->checkToken($request);
        $member = $this->memberId($request->request->getString('member'));

        if (!$this->notes->remove($id)) {
            throw new NotFoundHttpException('No such note.');
        }

        return $this->redirect($request, 'member', ['member' => $member->value]);
    }

    /**
     * The grounds of the decision, when the form gives a reason for the author.
     */
    private function grounds(Request $request): ?Grounds
    {
        $reason = $request->request->getString('ground');

        return '' === $reason ? null : new Grounds($reason, $request->request->getString('explanation'), $request->request->getString('reference'));
    }

    private function moderator(): Moderator
    {
        return $this->currentModerator->moderator() ?? throw $this->denied('Only a moderator opens the moderation.');
    }

    private function target(string $type, string $id): TargetReference
    {
        try {
            return TargetReference::of($type, $id);
        } catch (InvalidModeration) {
            throw new NotFoundHttpException('No such target.');
        }
    }

    private function memberId(string $member): MemberId
    {
        try {
            return MemberId::of($member);
        } catch (InvalidModeration) {
            throw new NotFoundHttpException('No such member.');
        }
    }

    private function checkToken(Request $request): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    private function denied(string $message): AccessDeniedException
    {
        return new AccessDeniedException($message);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function redirect(Request $request, string $page, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($this->routes($request)[$page], $parameters), Response::HTTP_SEE_OTHER);
    }

    /**
     * The routes of the pages, by their part: the bundle's, or those of the dashboard the request came through.
     *
     * @return array<string, string>
     */
    private function routes(Request $request): array
    {
        $own = [
            'queue' => self::QUEUE_ROUTE,
            'entry' => self::ENTRY_ROUTE,
            'decide' => self::DECIDE_ROUTE,
            'member' => self::MEMBER_ROUTE,
            'note' => self::NOTE_ROUTE,
            'note_remove' => self::NOTE_REMOVE_ROUTE,
        ];
        $dashboard = ModerationAdmin::dashboardRoute($request);

        if (null === $dashboard) {
            return $own;
        }

        return array_map(static fn (string $route): string => $dashboard.ModerationAdmin::ROUTES[$route], $own);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(Request $request, string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => null === ModerationAdmin::dashboardRoute($request) ? $this->layout : ModerationAdmin::LAYOUT,
            'routes' => $this->routes($request),
            'csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'incidents_route' => true === $this->incidents?->isOffered() ? IncidentProposal::ROUTE : null,
            'statements' => $this->statements,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
