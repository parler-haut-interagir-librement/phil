<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Oidc\ClientAssertions;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The credentials a client sends to the token, revocation and introspection endpoints: HTTP Basic — each part
 * form-decoded, as RFC 6749, section 2.3.1 asks —, `client_id` and `client_secret` in the form body, or
 * `client_assertion_type` and `client_assertion` (RFC 7523, section 2.2) — `client_id` then optional, the assertion's
 * `sub` naming the client until its signature is verified —; a public client sends its identifier alone. Two methods
 * at once are refused.
 */
final readonly class ClientCredentials
{
    private function __construct(
        public string $clientId,
        #[SensitiveParameter]
        public ?string $secret,
        public bool $basic,
        public ClientAuthMethod $method = ClientAuthMethod::None,
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
        $assertionType = $body['client_assertion_type'] ?? null;
        $assertion = $body['client_assertion'] ?? null;

        if (null !== $assertionType || null !== $assertion) {
            return self::fromAssertion($header, $bodyId, $bodySecret, $assertionType, $assertion);
        }

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

            return new self(urldecode($parts[0]), urldecode($parts[1]), true, ClientAuthMethod::ClientSecretBasic);
        }

        if (!\is_string($bodyId) || '' === $bodyId || (null !== $bodySecret && !\is_string($bodySecret))) {
            throw OAuthError::invalidRequest('The client identifies itself.');
        }

        return \is_string($bodySecret) ? new self($bodyId, $bodySecret, false, ClientAuthMethod::ClientSecretPost) : new self($bodyId, null, false);
    }

    /**
     * @throws OAuthError `invalid_request` with another method beside, `invalid_client` for an assertion of another type,
     *                    one that names no client, or one whose client differs from `client_id`
     */
    private static function fromAssertion(string $header, mixed $bodyId, mixed $bodySecret, mixed $type, #[SensitiveParameter] mixed $assertion): self
    {
        if ('' !== $header || null !== $bodySecret) {
            throw OAuthError::invalidRequest('The client authenticates by one method only.');
        }

        if (ClientAssertions::TYPE !== $type || !\is_string($assertion)) {
            throw OAuthError::invalidClient();
        }

        try {
            $subject = Jws::unverifiedClaims($assertion)['sub'] ?? null;
        } catch (MalformedInput) {
            $subject = null;
        }

        if (!\is_string($subject) || '' === $subject || null !== $bodyId && $bodyId !== $subject) {
            throw OAuthError::invalidClient();
        }

        return new self($subject, $assertion, false, ClientAuthMethod::PrivateKeyJwt);
    }
}
