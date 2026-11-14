<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\Url\Url;

/**
 * Reads the DID document a `did:web` names, at its HTTPS address. `SafeHttpFetcher` reads it through `phil/safe-http`;
 * an application decorates it to keep the documents a while, or replaces it in its tests.
 */
interface DocumentFetcher
{
    /**
     * The body of the document, as it was served.
     *
     * @throws InvalidCredential when the document cannot be read: unreachable, refused, not found, too large
     */
    public function fetch(Url $url): string;
}
