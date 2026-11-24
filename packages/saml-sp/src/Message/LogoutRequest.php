<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

/**
 * A logout request of the identity provider, verified: its ID, which the response repeats, its issuer, the name
 * identifier of the person to sign out and the sessions it names.
 */
final readonly class LogoutRequest
{
    /**
     * @param list<string> $sessionIndexes
     */
    public function __construct(
        public string $id,
        public string $issuer,
        public string $nameId,
        public ?string $nameIdFormat,
        public array $sessionIndexes,
    ) {
    }
}
