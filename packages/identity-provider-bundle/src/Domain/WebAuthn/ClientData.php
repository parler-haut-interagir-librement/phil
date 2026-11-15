<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use JsonException;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;

/**
 * The client data the browser signs over: the ceremony, the challenge and the origin. A ceremony of another type, a
 * challenge other than the one issued, an origin outside those allowed, or a cross-origin frame is refused.
 */
final readonly class ClientData
{
    public const int MAX_LENGTH = 4096;

    private function __construct(
        public string $type,
        public string $challenge,
        public string $origin,
        public bool $crossOrigin,
    ) {
    }

    public static function parse(string $json): self
    {
        if (\strlen($json) > self::MAX_LENGTH) {
            throw MalformedInput::because('The client data is too long.');
        }

        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw MalformedInput::because('The client data is not JSON.');
        }

        if (!\is_array($data) || !\is_string($data['type'] ?? null) || !\is_string($data['challenge'] ?? null) || !\is_string($data['origin'] ?? null)) {
            throw MalformedInput::because('The client data has a type, a challenge and an origin.');
        }

        return new self($data['type'], $data['challenge'], $data['origin'], true === ($data['crossOrigin'] ?? false));
    }

    /**
     * @param list<string> $origins
     */
    public function assert(string $type, string $challenge, array $origins): void
    {
        if ($this->type !== $type) {
            throw VerificationFailed::because('The client data is of another ceremony.');
        }

        if (!hash_equals($challenge, $this->challenge)) {
            throw VerificationFailed::because('The client data signs another challenge.');
        }

        if (!\in_array($this->origin, $origins, true) || $this->crossOrigin) {
            throw VerificationFailed::because('The client data comes from an origin that is not allowed.');
        }
    }
}
