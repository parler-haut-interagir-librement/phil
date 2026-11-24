<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

/**
 * What a link was found to be.
 *
 * - `Alive`: the address answered with a success, without a redirect.
 * - `PermanentRedirect`: it answered with a success after redirects that are all permanent (301, 308): the link can be
 *   replaced by its target.
 * - `TemporaryRedirect`: it answered with a success after a redirect at least one of which is temporary (302, 303,
 *   307).
 * - `Dead`: it answered 404 or 410, or its host name does not exist.
 * - `MissingAnchor`: the page of an internal link was found, but not the element its `#fragment` names.
 * - `Invalid`: the address as written cannot be read as a web address.
 * - `Review`: it could not be told — 429, 403, 401, a server error, a timeout, a TLS error, too many redirects; never
 *   counted as dead.
 * - `Refused`: the address is not a public one — a private network, a loopback, an IP address, credentials —, and was
 *   not requested.
 */
enum LinkStatus: string
{
    case Alive = 'alive';
    case PermanentRedirect = 'permanent-redirect';
    case TemporaryRedirect = 'temporary-redirect';
    case Dead = 'dead';
    case MissingAnchor = 'missing-anchor';
    case Invalid = 'invalid';
    case Review = 'review';
    case Refused = 'refused';

    /**
     * Whether the link is broken and should be fixed: dead, a missing anchor, or an address that cannot be read.
     */
    public function isBroken(): bool
    {
        return match ($this) {
            self::Dead, self::MissingAnchor, self::Invalid => true,
            default => false,
        };
    }

    public function isRedirect(): bool
    {
        return self::PermanentRedirect === $this || self::TemporaryRedirect === $this;
    }
}
