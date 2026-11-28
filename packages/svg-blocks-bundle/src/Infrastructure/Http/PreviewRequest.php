<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a preview request comes from a page of this site. It carries the preview controller's own header, which a
 * form or a link of another site cannot add without the browser asking this site first; the browser does not say it
 * was sent from another site; and it passes Symfony's stateless CSRF check of the token `phil_svg_blocks`: a token
 * sent both in a header and in a cookie, or the browser's `Sec-Fetch-Site`, `Origin` or `Referer` naming this site.
 * The route still sits behind the application's firewall: this check only keeps another site from calling it in a
 * writer's name.
 */
final readonly class PreviewRequest
{
    public const string HEADER = 'X-Phil-Svg-Blocks';

    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_svg_blocks';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The name of the cookie and of the header that carry the token, given to the preview controller.
     */
    public function cookieName(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        return '1' === $request->headers->get(self::HEADER)
            && 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->headers->get($this->cookieName()) ?? ''));
    }
}
