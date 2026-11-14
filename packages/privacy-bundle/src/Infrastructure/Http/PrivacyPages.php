<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * What the pages of the bundle share: their templates under `@PhilPrivacy/`, the layout they extend, the stateless CSRF
 * token of their forms, so that a visitor who is only asked about cookies gets no session, and the `303` after a form.
 */
final readonly class PrivacyPages
{
    public const string CSRF_TOKEN_ID = 'phil_privacy';

    public const int MAX_TARGET_LENGTH = 2048;

    public function __construct(
        private Environment $twig,
        private CsrfTokenManagerInterface $csrfTokens,
        private UrlGeneratorInterface $urls,
        private string $layout,
    ) {
    }

    /**
     * A page with a form, never kept by a cache.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        return new Response(
            $this->twig->render('@PhilPrivacy/'.$template.'.html.twig', $context + ['layout' => $this->layout, 'csrf_token' => $this->csrfToken()]),
            $status,
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * A person's export, as a download that no cache keeps and no browser opens in place.
     */
    public static function archive(string $zip): Response
    {
        return new Response($zip, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="personal-data.zip"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function csrfToken(): string
    {
        return $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue();
    }

    public function guardForm(Request $request): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    public function seeOther(string $route): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route), Response::HTTP_SEE_OTHER);
    }

    /**
     * A path of this site, or null for anything else: a form never sends a person to another host.
     */
    public static function localTarget(string $target): ?string
    {
        $safe = str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_starts_with($target, '/\\')
            && \strlen($target) <= self::MAX_TARGET_LENGTH
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $target);

        return $safe ? $target : null;
    }
}
