<?php

declare(strict_types=1);

namespace Phil\Lti\Tool;

use Phil\Lti\Contract\LoginStates;
use Phil\Lti\Contract\Nonces;
use Phil\Lti\Contract\PlatformRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;

/**
 * The second step of a launch on the tool: the `id_token` and the `state` the platform posts back.
 *
 * - the state is taken from the store, once, and must not have expired;
 * - the token names the issuer and the client the state was made for: `iss` equal, `aud` the client or a list that
 *   holds it, `azp` the client when present, required when `aud` lists several;
 * - the signature verifies with the platform's key, read from its key set, by `RS256` only;
 * - `exp` and `iat` hold, by the clock;
 * - the `nonce` is the one of the state, and it is used once;
 * - the deployment is registered for the platform;
 * - the claims of LTI are well formed for the message type, and the target of the launch is the one asked for.
 */
final readonly class LaunchValidator
{
    public const string NONCE_SCOPE = 'id_token';

    public function __construct(
        private PlatformRegistrations $platforms,
        private LoginStates $states,
        private Nonces $nonces,
        private JwtVerifier $verifier,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @throws InvalidRequest      when the state is unknown, taken or expired
     * @throws InvalidToken        when the token fails a check
     * @throws UnknownRegistration when the platform or the deployment is not registered
     */
    public function validate(string $idToken, string $state): Launch
    {
        $remembered = $this->states->take($state) ?? throw InvalidRequest::because('The state of the launch is unknown, already used or expired.');
        $platform = $this->platforms->find($remembered->issuer, $remembered->clientId) ?? throw UnknownRegistration::platform($remembered->issuer, $remembered->clientId);
        $jwt = Jwt::parse($idToken);
        self::checkAudience($jwt, $platform->issuer, $platform->clientId);
        $jwt = $this->verifier->verify($idToken, $platform->keySetUrl);

        $nonce = $jwt->claims['nonce'] ?? null;

        if (!\is_string($nonce) || !hash_equals($remembered->nonce, $nonce)) {
            throw InvalidToken::because('The nonce of the token is not the one of the login.');
        }

        if (!$this->nonces->use(self::NONCE_SCOPE, $platform->key()."\n".$nonce, $this->verifier->expiryOfNonce())) {
            throw InvalidToken::because('The nonce of the token was already used.');
        }

        $message = LaunchMessage::fromClaims($jwt->claims, $this->allowLocalhost);

        if (!$this->platforms->hasDeployment($platform, $message->deploymentId)) {
            throw UnknownRegistration::deployment($message->deploymentId);
        }

        if ($message->targetLinkUri->toString() !== $remembered->targetLinkUri) {
            throw InvalidToken::because('The target of the launch is not the one of the login.');
        }

        return new Launch($platform->issuer, $platform->clientId, $message, $jwt->claims);
    }

    /**
     * The issuer and the audience of a token, before its signature: a token meant for another client is refused without
     * reading the key set.
     *
     * @throws InvalidToken
     */
    public static function checkAudience(Jwt $jwt, string $issuer, string $clientId): void
    {
        if (($jwt->claims['iss'] ?? null) !== $issuer) {
            throw InvalidToken::because('The token is not issued by the platform of the login.');
        }

        $audience = $jwt->claims['aud'] ?? null;
        $audiences = \is_string($audience) ? [$audience] : (\is_array($audience) ? $audience : []);

        if (!\in_array($clientId, $audiences, true)) {
            throw InvalidToken::because('The token is not addressed to the client of the tool.');
        }

        $authorized = $jwt->claims['azp'] ?? null;

        if ((null !== $authorized || \count($audiences) > 1) && $authorized !== $clientId) {
            throw InvalidToken::because('The authorized party of the token is not the client of the tool.');
        }

        if (!\is_string($jwt->claims['sub'] ?? '')) {
            throw InvalidToken::because('The subject of the token is a string.');
        }
    }
}
