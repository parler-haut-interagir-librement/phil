<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * Where a member stands with the code of a scope.
 */
enum AcceptanceStatus: string
{
    /** The scope has no code: nothing to accept. */
    case NoCode = 'no_code';

    /** The member never accepted the code. */
    case NotAccepted = 'not_accepted';

    /** The member accepted a version older than the latest major one: they accept it again. */
    case Outdated = 'outdated';

    /** The member accepted the latest major version, or a later one. */
    case Accepted = 'accepted';

    /**
     * Whether the member may take part: write, register, post. Reading is never held back by the code.
     */
    public function mayTakePart(): bool
    {
        return self::Accepted === $this || self::NoCode === $this;
    }
}
