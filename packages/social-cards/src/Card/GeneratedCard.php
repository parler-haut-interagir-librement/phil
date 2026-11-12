<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

/**
 * A drawn card: its fingerprint and its PNG.
 */
final readonly class GeneratedCard
{
    public function __construct(
        public string $fingerprint,
        public string $png,
    ) {
    }
}
