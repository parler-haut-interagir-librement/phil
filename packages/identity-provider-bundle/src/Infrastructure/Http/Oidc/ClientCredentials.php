<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The credentials a client sends to the token, revocation and introspection endpoints: HTTP Basic — each part
 * form-decoded, as RFC 6749, section 2.3.1 asks — or `client_id` and `client_secret` in the form body; a public client
 * sends its identifier alone. Two methods at once are refused.
 */
final readonly class ClientCredentials
{
    private function __construct(
        public string $clientId,
        #[SensitiveParameter]
        public ?string $secret,
        public bool $basic,
    ) {
    }

    /**
     * @throws OAuthError `invalid_request` when no identifier is sent, or two methods are
     */
    public static function from(Request $request): self
    {
        $header = $request->headers->get('Authorization') ?? '';
        $body = $request->request->all();
        $bodyId = $body['client_id'] ?? null;
        $bodySecret = $body['client_secret'] ?? null;

        if (str_starts_with(strtolower($header), 'basic ')) {
            if (null !== $bodySecret) {
                throw OAuthError::invalidRequest('The client authenticates by one method only.');
            }

            try {
                $parts = explode(':', sodium_base642bin(trim(substr($header, 6)), \SODIUM_BASE64_VARIANT_ORIGINAL), 2);
            } catch (SodiumException) {
                $parts = [];
            }

            if (2 !== \count($parts) || '' === $parts[0]) {
                throw OAuthError::invalidClient();
            }

            return new self(urldecode($parts[0]), urldecode($parts[1]), true);
        }

        if (!\is_string($bodyId) || '' === $bodyId || (null !== $bodySecret && !\is_string($bodySecret))) {
            throw OAuthError::invalidRequest('The client identifies itself.');
        }

        return new self($bodyId, \is_string($bodySecret) ? $bodySecret : null, false);
    }
}
