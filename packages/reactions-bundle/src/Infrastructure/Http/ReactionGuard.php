<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Http;

use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a change has to pass before it runs: Symfony's stateless CSRF check of the token `phil_reactions`; and, for a
 * plain form, the signature of its address, so that a browser can change neither the content it reacts to nor the page
 * it comes back to.
 */
final readonly class ReactionGuard
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_reactions';

    public const string ROUTE = 'phil_reactions_submit';

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
     * @throws ReactionRefused when the check does not pass
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw ReactionRefused::untrustedRequest();
        }
    }

    /**
     * The signed address a plain form posts to.
     */
    public function action(string $content, string $back): string
    {
        return $this->signer->sign($this->urls->generate(self::ROUTE, ['content' => $content, 'back' => $back], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function isSigned(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }
}
