<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Http;

use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * What every page of the administration shares: the role that opens it, the CSRF token of its forms, the languages it
 * edits, its layout, and the `303` that follows a change.
 *
 * A user without the role meets the firewall, as on any denied page; a form without a valid token is refused.
 */
final readonly class AdminGuard
{
    public const string CSRF_TOKEN_ID = 'phil_faq_admin';

    /**
     * @param non-empty-list<string> $locales the languages edited, the first one by default
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $role,
        private string $layout,
        private array $locales,
    ) {
    }

    public function denyUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }

    /**
     * The role, then the token of a posted form.
     *
     * @throws BadRequestHttpException when the token is not valid
     */
    public function guardForm(Request $request): void
    {
        $this->denyUnlessGranted();

        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    /**
     * The language a page edits: the `locale` of its address when it is one of the configured ones, else the first.
     */
    public function locale(Request $request): Locale
    {
        $asked = $request->query->getString('locale', $request->request->getString('locale'));

        return Locale::fromString(\in_array($asked, $this->locales, true) ? $asked : $this->locales[0]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render('@PhilFaq/admin/'.$template.'.html.twig', $context + [
            'layout' => $this->layout,
            'csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'locales' => $this->locales,
        ]), $status, ['Cache-Control' => 'no-store']);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function seeOther(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }

    /**
     * Checks the configured languages.
     *
     * @param list<string> $locales
     *
     * @return non-empty-list<string>
     *
     * @throws InvalidFaq when one is not a locale
     */
    public static function locales(array $locales): array
    {
        $normalized = array_values(array_unique(array_map(static fn (string $locale): string => Locale::fromString($locale)->value, $locales)));

        return [] === $normalized ? ['en'] : $normalized;
    }
}
