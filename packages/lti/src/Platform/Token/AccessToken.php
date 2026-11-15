<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Token;

use DateTimeImmutable;

/**
 * An access token the platform granted a tool: kept by the SHA-256 of its value, never the value itself, with the client,
 * the scopes and the moment it expires.
 */
final readonly class AccessToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $hash,
        public string $clientId,
        public array $scopes,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function grants(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }
}
