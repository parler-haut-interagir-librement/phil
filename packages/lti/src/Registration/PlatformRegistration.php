<?php

declare(strict_types=1);

namespace Phil\Lti\Registration;

use InvalidArgumentException;
use Phil\Url\Url;

/**
 * A platform registered with the tool: its issuer, the client it gave the tool, and its endpoints — where the tool
 * sends the person to authenticate, where it asks for access tokens, and where its keys are published.
 */
final readonly class PlatformRegistration
{
    /**
     * @param string|null $tokenAudience the `aud` of the client assertions sent to the token endpoint; the address of
     *                                   the token endpoint when null
     */
    public function __construct(
        public string $issuer,
        public string $clientId,
        public Url $authorizationUrl,
        public Url $tokenUrl,
        public Url $keySetUrl,
        public ?string $tokenAudience = null,
    ) {
        if ('' === $issuer || \strlen($issuer) > 1024 || '' === $clientId || \strlen($clientId) > 255) {
            throw new InvalidArgumentException('A platform is registered by its issuer, of 1 to 1,024 bytes, and its client, of 1 to 255 bytes.');
        }
    }

    /**
     * A stable key of the registration, for the stores and the caches.
     */
    public function key(): string
    {
        return hash('sha256', $this->issuer."\n".$this->clientId);
    }

    public function audience(): string
    {
        return $this->tokenAudience ?? $this->tokenUrl->toString();
    }
}
