<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Contract\Nonces;
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Support\Fields;

/**
 * Reads the deep linking response a tool posts back to the platform, in its `JWT` field: `iss` a registered client,
 * `aud` the platform, signed by the tool's key, `exp` and `iat` holding, the `nonce` used once, the deployment the tool's,
 * the message type and version those of a response; then the content items. The nonce is taken once: a response read
 * is never read again from its token, and an application that has to handle it again keeps its `DeepLinkingResult`
 * claims and reads them with `DeepLinkingResult::fromClaims()`.
 */
final readonly class DeepLinkingReturn
{
    public const string NONCE_SCOPE = 'deep_linking_response';

    public function __construct(
        private string $issuer,
        private ToolRegistrations $tools,
        private Nonces $nonces,
        private JwtVerifier $verifier,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @throws InvalidToken|UnknownRegistration
     */
    public function read(string $token): DeepLinkingResult
    {
        $jwt = Jwt::parse($token);
        $clientId = $jwt->claims['iss'] ?? null;

        if (!\is_string($clientId)) {
            throw InvalidToken::because('A deep linking response is issued by the client of the tool.');
        }

        $tool = $this->tools->find($clientId) ?? throw UnknownRegistration::tool($clientId);
        $audience = $jwt->claims['aud'] ?? null;

        if ($audience !== $this->issuer && (!\is_array($audience) || !\in_array($this->issuer, $audience, true))) {
            throw InvalidToken::because('The deep linking response is not addressed to the platform.');
        }

        $jwt = $this->verifier->verify($token, $tool->keySetUrl);
        $fields = new Fields($jwt->claims, allowLocalhost: $this->allowLocalhost);

        if (MessageType::DeepLinkingResponse->value !== $fields->optionalString(Claim::MESSAGE_TYPE, 64) || Claim::LTI_VERSION !== $fields->optionalString(Claim::VERSION, 16)) {
            throw InvalidToken::because('The token is not a deep linking response of LTI 1.3.');
        }

        $nonce = $fields->string('nonce', 255);

        if (!$this->nonces->use(self::NONCE_SCOPE, $clientId."\n".$nonce, $this->verifier->expiryOfNonce())) {
            throw InvalidToken::because('The nonce of the deep linking response was already used.');
        }

        $deploymentId = $fields->string(Claim::DEPLOYMENT_ID, 255);

        if (!$this->tools->hasDeployment($tool, $deploymentId)) {
            throw UnknownRegistration::deployment($deploymentId);
        }

        return DeepLinkingResult::fromClaims($jwt->claims, $this->allowLocalhost);
    }
}
