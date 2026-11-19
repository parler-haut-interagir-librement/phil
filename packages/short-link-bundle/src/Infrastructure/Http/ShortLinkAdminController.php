<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\Http;

use Phil\ShortLinkBundle\Application\Exception\ShortLinkNotFound;
use Phil\ShortLinkBundle\Application\Exception\ShortLinkRefused;
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Application\ShortLinkQrCodes;
use Phil\ShortLinkBundle\Application\ShortLinks;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Infrastructure\Form\ShortLinkType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The short links in the administration: the list with its search, the form that creates or changes one, the buttons
 * that enable, disable or remove one, and the QR code of each, as SVG or PNG. Every page needs the configured role;
 * every change goes through `ShortLinks`, whose refusals the form shows on their field, and every button posts a CSRF
 * token. The pages use the class names of the PHIL UX kit, which its stylesheet draws, inside the configured layout.
 */
final readonly class ShortLinkAdminController
{
    public const string LIST_ROUTE = 'phil_short_link_admin_list';

    public const string CREATE_ROUTE = 'phil_short_link_admin_create';

    public const string EDIT_ROUTE = 'phil_short_link_admin_edit';

    public const string CSRF_TOKEN_ID = 'phil_short_link_admin';

    public const int PAGE_SIZE = 50;

    /** The outcomes the list reports after a change: one of these, never what a crafted link puts in the query string. */
    private const array OUTCOMES = ['created', 'updated', 'deleted', 'enabled', 'disabled'];

    /** The field of the form each refusal concerns; the others are shown on the form. */
    private const array FIELDS = [
        'target' => 'target', 'target_loop' => 'target', 'target_host' => 'target', 'target_unreachable' => 'target',
        'code_custom' => 'code', 'code_invalid' => 'code', 'code_blocked' => 'code', 'code_taken' => 'code',
        'locale' => 'locale', 'dates' => 'validUntil', 'max_visits' => 'maxVisits', 'label' => 'label', 'status' => 'status',
    ];

    public function __construct(
        private ShortLinks $links,
        private ShortLinkRepository $repository,
        private ShortLinkQrCodes $qrCodes,
        private FormFactoryInterface $forms,
        private AuthorizationCheckerInterface $authorizationChecker,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        private string $role = 'ROLE_ADMIN',
        private string $layout = '@PhilShortLink/admin/layout.html.twig',
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->denyUnlessGranted();
        $search = mb_substr(trim($request->query->getString('q')), 0, 200);
        $page = max(1, min(100_000, $request->query->getInt('page', 1)));
        $done = $request->query->getString('done');
        $links = $this->repository->page($search, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        return $this->render('list', [
            'links' => array_map(fn (ShortLink $link): array => ['link' => $link, 'url' => $this->links->url($link), 'state' => $link->stateAt($this->clock->now())], $links),
            'search' => $search,
            'page' => $page,
            'pages' => max(1, (int) ceil($this->repository->count($search) / self::PAGE_SIZE)),
            'done' => \in_array($done, self::OUTCOMES, true) ? $done : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->denyUnlessGranted();
        $draft = new ShortLinkDraft();
        $form = $this->forms->create(ShortLinkType::class, $draft);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->links->create($draft, $this->author($request));

                return $this->done('created');
            } catch (ShortLinkRefused $refused) {
                $this->refuse($form, $refused);
            }
        }

        return $this->render('form', ['form' => $form->createView(), 'link' => null], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->denyUnlessGranted();
        $link = $this->link($id);
        $draft = ShortLinkDraft::of($link);
        $form = $this->forms->create(ShortLinkType::class, $draft, ['existing' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->links->update($id, $draft);

                return $this->done('updated');
            } catch (ShortLinkRefused $refused) {
                $this->refuse($form, $refused);
            }
        }

        return $this->render('form', ['form' => $form->createView(), 'link' => $link, 'url' => $this->links->url($link)], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->guardButton($request);
        $enabled = '1' === $request->request->getString('enabled');

        try {
            $this->links->enable($id, $enabled);
        } catch (ShortLinkNotFound $shortLinkNotFound) {
            throw new NotFoundHttpException($shortLinkNotFound->getMessage(), $shortLinkNotFound);
        }

        return $this->done($enabled ? 'enabled' : 'disabled');
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        $this->guardButton($request);

        try {
            $this->links->delete($id);
        } catch (ShortLinkNotFound $shortLinkNotFound) {
            throw new NotFoundHttpException($shortLinkNotFound->getMessage(), $shortLinkNotFound);
        }

        return $this->done('deleted');
    }

    public function qrCode(int $id, string $format): Response
    {
        $this->denyUnlessGranted();
        $link = $this->link($id);
        $png = 'png' === $format;
        $response = new Response($png ? $this->qrCodes->png($link) : $this->qrCodes->svg($link));
        $response->headers->set('Content-Type', $png ? 'image/png' : 'image/svg+xml');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('inline', 'short-link-'.$link->code().'.'.$format));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }

    private function guardButton(Request $request): void
    {
        $this->denyUnlessGranted();

        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    private function link(int $id): ShortLink
    {
        try {
            return $this->links->get($id);
        } catch (ShortLinkNotFound $shortLinkNotFound) {
            throw new NotFoundHttpException($shortLinkNotFound->getMessage(), $shortLinkNotFound);
        }
    }

    /**
     * Who creates a link, for the allowance of creations: the signed-in account, or else the client's address — hashed
     * by the throttle, never kept.
     */
    private function author(Request $request): string
    {
        $identifier = $this->tokens?->getToken()?->getUserIdentifier();

        return null !== $identifier && '' !== $identifier ? 'user:'.$identifier : 'client:'.($request->getClientIp() ?? '');
    }

    /**
     * @param FormInterface<ShortLinkDraft> $form
     */
    private function refuse(FormInterface $form, ShortLinkRefused $refused): void
    {
        $field = self::FIELDS[$refused->reason] ?? null;
        $target = null !== $field && $form->has($field) ? $form->get($field) : $form;
        $target->addError(new FormError($this->translator->trans('refused.'.$refused->reason, $refused->parameters, 'phil_short_link'), $refused->template, $refused->parameters));
    }

    private function done(string $outcome): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE, ['done' => $outcome]), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $page, array $parameters, int $status = Response::HTTP_OK): Response
    {
        $response = new Response($this->twig->render('@PhilShortLink/admin/'.$page.'.html.twig', [...$parameters, 'layout' => $this->layout, 'csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue()]), $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
