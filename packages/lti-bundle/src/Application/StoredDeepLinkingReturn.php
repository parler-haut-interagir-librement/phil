<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Platform\DeepLinkingResult;

/**
 * A deep linking response the platform verified, as it is kept: its key, its client, the claims it was verified with,
 * when it came, until when it is kept, and when the application handled it — null until then.
 */
final readonly class StoredDeepLinkingReturn
{
    /**
     * @param array<array-key, mixed> $claims
     */
    public function __construct(
        public string $key,
        public string $clientId,
        public array $claims,
        public DateTimeImmutable $receivedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $handledAt = null,
    ) {
    }

    public static function of(DeepLinkingResult $result, DateTimeImmutable $receivedAt, DateTimeImmutable $expiresAt): self
    {
        return new self($result->key, $result->clientId, $result->claims, $receivedAt, $expiresAt);
    }

    /**
     * The response, read again from its verified claims: its signature and its nonce are not checked again.
     *
     * @throws InvalidToken when the claims kept are not those of a response
     */
    public function result(bool $allowLocalhost = false): DeepLinkingResult
    {
        return DeepLinkingResult::fromClaims($this->claims, $allowLocalhost);
    }
}
