<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * Gives the JSON-LD context a credential names by URL, as its decoded document. The library's own loader serves the
 * contexts it bundles and refuses every other URL, so that verifying a credential never fetches anything.
 */
interface ContextLoader
{
    /**
     * @return array<array-key, mixed> the decoded context document, with its `@context` entry
     *
     * @throws InvalidCredential when the context is unknown
     */
    public function load(string $url): array;
}
