<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Http;

use Phil\AccessibilityStatementBundle\Application\Exception\PublicationRefused;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The administration page of the statement: its versions, and a form that publishes the next one from a draft — the
 * version the latest audit gives, or the version in force renumbered —, for those granted `$role`: a visitor is asked to
 * sign in by the firewall, a person without the role refused. Without Symfony Security's authorization checker, or
 * without its CSRF token manager for the form, nobody is let in: the page is refused rather than open. A version published redirects to the page with `?published={n}`; a document refused comes
 * back with the reason, `422`.
 */
final readonly class StatementAdminController
{
    public const string ROUTE = 'phil_accessibility_statement_admin';

    public const string PUBLISH_ROUTE = 'phil_accessibility_statement_admin_publish';

    public const string TEMPLATE = '@PhilAccessibilityStatement/admin.html.twig';

    public const string CSRF_TOKEN = 'phil_accessibility_statement_publish';

    public function __construct(
        private StatementRepository $statements,
        private StatementPublisher $publisher,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private string $role,
        private ?AuthorizationCheckerInterface $authorization = null,
        private ?CsrfTokenManagerInterface $csrf = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyUnlessGranted();

        return $this->page($this->publisher->draft(), null, $request->query->getInt('published'), Response::HTTP_OK);
    }

    public function publish(Request $request): Response
    {
        $this->denyUnlessGranted();
        $token = $request->request->getString('_token');

        if (!$this->csrf instanceof CsrfTokenManagerInterface || !$this->csrf->isTokenValid(new CsrfToken(self::CSRF_TOKEN, $token))) {
            throw new AccessDeniedHttpException('The form of the accessibility statement expired: reload the page.');
        }

        $document = $request->request->getString('statement');

        try {
            $statement = $this->publisher->publish($document);
        } catch (PublicationRefused $publicationRefused) {
            return $this->page($document, $publicationRefused->getMessage(), 0, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->urls->generate(self::ROUTE, ['published' => $statement->version]), Response::HTTP_SEE_OTHER);
    }

    /**
     * Symfony Security's own refusal, so that its firewall asks a visitor to sign in and refuses a person signed in
     * without the role.
     */
    private function denyUnlessGranted(): void
    {
        if (!$this->authorization instanceof AuthorizationCheckerInterface) {
            throw new AccessDeniedHttpException('The administration of the accessibility statement needs Symfony Security.');
        }

        if (!$this->authorization->isGranted($this->role)) {
            $denied = new AccessDeniedException('The administration of the accessibility statement is not open to you.');
            $denied->setAttributes([$this->role]);

            throw $denied;
        }
    }

    private function page(?string $draft, ?string $error, int $published, int $status): Response
    {
        $html = $this->twig->render(self::TEMPLATE, [
            'layout' => $this->layout,
            'latest' => $this->statements->latest(),
            'versions' => $this->statements->publications(StatementController::MAX_VERSIONS),
            'writable' => $this->statements->writable(),
            'draft' => $draft,
            'error' => $error,
            'published' => $published,
            'csrf_token' => $this->csrf?->getToken(self::CSRF_TOKEN)->getValue() ?? '',
        ]);

        return new Response($html, $status, ['Cache-Control' => 'no-store']);
    }
}
