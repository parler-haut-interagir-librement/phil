<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * What the pages of the bundle share: their Twig templates under `@PhilTerms/`, the layout they extend, the CSRF token
 * of their forms — only on a page with a form, so that a public page opens no session —, the `303` that follows a
 * form, and the local address a member goes back to.
 */
final readonly class Pages
{
    public const string CSRF_TOKEN_ID = 'phil_terms';

    public const int MAX_TARGET_LENGTH = 2048;

    public function __construct(
        private Environment $twig,
        private CsrfTokenManagerInterface $csrfTokens,
        private UrlGeneratorInterface $urls,
        private string $layout,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK, string $cacheControl = 'no-store', ?string $layout = null): Response
    {
        return new Response($this->twig->render('@PhilTerms/'.$template.'.html.twig', $context + ['layout' => $layout ?? $this->layout]), $status, ['Cache-Control' => $cacheControl]);
    }

    /**
     * A page with a form, its CSRF token in the `csrf_token` variable, never kept by a cache.
     *
     * @param array<string, mixed> $context
     */
    public function form(string $template, array $context = [], int $status = Response::HTTP_OK, ?string $layout = null): Response
    {
        return $this->render($template, $context + ['csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue()], $status, 'no-store', $layout);
    }

    /**
     * Refuses a posted form without the token of the bundle's forms.
     */
    public function guardForm(Request $request): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    /**
     * @param array<string, int|string> $parameters
     */
    public function seeOther(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }

    /**
     * The address of the site a member goes back to: a path of this site, never another host — `/` for anything else.
     */
    public static function target(string $target): string
    {
        $safe = str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_starts_with($target, '/\\')
            && \strlen($target) <= self::MAX_TARGET_LENGTH
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $target);

        return $safe ? $target : '/';
    }
}
