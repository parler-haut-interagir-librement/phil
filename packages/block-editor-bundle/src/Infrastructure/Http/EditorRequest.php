<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a request comes from the editor of this site. It carries the editor's own header, which a form or a link of
 * another site cannot add without the browser asking this site first; the browser does not say it was sent from
 * another site; and, unless its method is safe, it passes Symfony's stateless CSRF check of the token
 * `phil_block_editor`: a token the editor sends both in a header and in a cookie, or the browser's `Sec-Fetch-Site`,
 * `Origin` or `Referer` naming this site. A `GET` search changes nothing, so it carries no token. That check only keeps
 * another site from calling the endpoints in the writer's name: anyone can send those headers from a script.
 *
 * Who may call them at all is the application's security: the attribute `$access`, `IS_AUTHENTICATED` by default, is
 * granted by its authorization checker, or the request is refused, as it is when there is no checker to ask. A null
 * attribute leaves the endpoints to the application's own rules, an `access_control` entry on their prefix.
 */
final readonly class EditorRequest
{
    public const string HEADER = 'X-Phil-Block-Editor';

    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_block_editor';

    /** The attribute granted to whoever may call the endpoints, by default: anyone signed in. */
    public const string ACCESS = 'IS_AUTHENTICATED';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        private ?AuthorizationCheckerInterface $authorization = null,
        private ?string $access = self::ACCESS,
    ) {
    }

    /**
     * Whether the current user may call the endpoints: the attribute granted, or no attribute to check.
     */
    public function isGranted(): bool
    {
        return null === $this->access || true === $this->authorization?->isGranted($this->access);
    }

    /**
     * The name of the cookie and of the header that carry the editor's token, given to its controller.
     */
    public function cookieName(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        return '1' === $request->headers->get(self::HEADER)
            && 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && ($request->isMethodSafe() || $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->headers->get($this->cookieName()) ?? '')));
    }
}
