<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * One thing an incident suspends, by a name the application checks: `signups`, `invitations`, `new-messages`, or a
 * name of its own.
 *
 * `read-only` is the widest: while it is in force, every restriction is, the application's own included.
 */
final readonly class Restriction
{
    public const string SIGNUPS = 'signups';

    public const string INVITATIONS = 'invitations';

    public const string NEW_MESSAGES = 'new-messages';

    public const string NEW_POSTS = 'new-posts';

    public const string NEW_COMMENTS = 'new-comments';

    public const string REACTIONS = 'reactions';

    public const string UPLOADS = 'uploads';

    public const string PROFILE_CHANGES = 'profile-changes';

    public const string READ_ONLY = 'read-only';

    /** A lowercase letter, then up to 63 lowercase letters, digits and hyphens. */
    public const string PATTERN = '/^[a-z][a-z0-9-]{0,63}$/D';

    private function __construct(
        public string $name,
    ) {
    }

    public static function named(string $name): self
    {
        if (1 !== preg_match(self::PATTERN, $name)) {
            throw InvalidIncident::because(\sprintf('"%s" is not a restriction name: a lowercase letter, then up to 63 lowercase letters, digits and hyphens.', $name));
        }

        return new self($name);
    }

    public function isReadOnly(): bool
    {
        return self::READ_ONLY === $this->name;
    }
}
