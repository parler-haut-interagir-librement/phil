<?php

declare(strict_types=1);

namespace Phil\SocialCards\Contract;

/**
 * Where the drawn cards are kept, by fingerprint — 32 lower-case hexadecimal digits —, so that each is drawn once.
 */
interface CardStore
{
    /**
     * The PNG of the card, or null when none is kept.
     */
    public function get(string $fingerprint): ?string;

    public function put(string $fingerprint, string $png): void;
}
