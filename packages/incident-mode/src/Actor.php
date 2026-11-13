<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * Who started, extended or lifted an incident, by the stable identifier the application gives its members: a UUID, an
 * OIDC `sub`, a member number.
 *
 * **Never an e-mail address**: an address changes and names the person to whoever reads the journal, so an identifier
 * that looks like one is refused.
 */
final readonly class Actor
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $id,
    ) {
    }

    public static function of(string $id): self
    {
        if ('' === $id || \strlen($id) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $id)) {
            throw InvalidIncident::because(\sprintf('An actor is designated by 1 to %d visible characters.', self::MAX_LENGTH));
        }

        if (1 === preg_match('/^[^@]+@[^@]+\.[^@]+$/D', $id)) {
            throw InvalidIncident::because('An actor is designated by a stable identifier, never an e-mail address.');
        }

        return new self($id);
    }
}
