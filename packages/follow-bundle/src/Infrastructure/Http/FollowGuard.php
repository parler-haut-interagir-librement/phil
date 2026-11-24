<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Http;

use Phil\FollowBundle\Application\Exception\FollowRefused;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a form has to pass before its change runs: Symfony's stateless CSRF check of the token `phil_follow`, and the
 * signature of the address it posts to, so that a browser can change neither the subject nor the page it comes back to.
 */
final readonly class FollowGuard
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_follow';

    public const string LEVEL = 'phil_follow_level';

    public const string REQUEST = 'phil_follow_request';

    public const string VISIBILITY = 'phil_follow_visibility';

    public const string MUTUAL = 'phil_follow_mutual';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        private UriSigner $signer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws FollowRefused when the token does not pass
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw FollowRefused::untrustedRequest();
        }
    }

    /**
     * The signed address a form posts to.
     *
     * @param array<string, string> $parameters
     */
    public function action(string $route, array $parameters): string
    {
        return $this->signer->sign($this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function isSigned(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }
}
