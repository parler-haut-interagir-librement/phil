<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a plain form of the comments on a page passes before it runs: the signature of the address it posts to, so that
 * a browser can change neither the page it comments on nor the page it comes back to; and Symfony's stateless CSRF
 * check of the token `phil_comments`, which a plain form passes by the browser's `Sec-Fetch-Site`, `Origin` or
 * `Referer` naming this site.
 */
final readonly class PageForm
{
    public const string ROUTE = 'phil_comments_page';

    /** The identifier of the section, the fragment a form comes back to. */
    public const string SECTION = 'phil-comments-page';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        private UriSigner $signer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(CommentsRequest::TOKEN_ID)->getValue();
    }

    public function isTrusted(string $token): bool
    {
        return $this->tokens->isTokenValid(new CsrfToken(CommentsRequest::TOKEN_ID, $token));
    }

    /**
     * The signed address the forms of a page post to.
     */
    public function action(string $subject, string $back): string
    {
        return $this->signer->sign($this->urls->generate(self::ROUTE, ['subject' => $subject, 'back' => $back], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function isSigned(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }
}
