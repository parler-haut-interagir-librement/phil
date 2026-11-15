<?php

declare(strict_types=1);

namespace Phil\Lti\Tool;

use Phil\Lti\Exception\InvalidRequest;

/**
 * The third-party initiated login a platform sends to the tool, by GET or POST: the issuer, the hint of the person, the
 * target of the launch, and, when the platform gives them, its own hint, the client and the deployment.
 */
final readonly class LoginRequest
{
    public function __construct(
        public string $issuer,
        public string $loginHint,
        public string $targetLinkUri,
        public ?string $messageHint = null,
        public ?string $clientId = null,
        public ?string $deploymentId = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $parameters the query or the body of the request
     *
     * @throws InvalidRequest when a required parameter is missing, or one is not a string of bounded length
     */
    public static function fromParameters(array $parameters): self
    {
        return new self(
            self::read($parameters, 'iss', 1024) ?? throw InvalidRequest::because('A login initiation names the issuer, "iss".'),
            self::read($parameters, 'login_hint', 2048) ?? throw InvalidRequest::because('A login initiation carries the "login_hint".'),
            self::read($parameters, 'target_link_uri', 2048) ?? throw InvalidRequest::because('A login initiation carries the "target_link_uri".'),
            self::read($parameters, 'lti_message_hint', 4096),
            self::read($parameters, 'client_id', 255),
            self::read($parameters, 'lti_deployment_id', 255),
        );
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private static function read(array $parameters, string $name, int $max): ?string
    {
        $value = $parameters[$name] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value) || \strlen($value) > $max || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidRequest::because(\sprintf('The parameter "%s" is a string of %d bytes at most.', $name, $max));
        }

        return $value;
    }
}
