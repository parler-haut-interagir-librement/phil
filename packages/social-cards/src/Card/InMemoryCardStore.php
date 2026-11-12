<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Contract\CardStore;

/**
 * Cards kept in memory, for the tests and for a process that draws a few cards and serves them itself.
 */
final class InMemoryCardStore implements CardStore
{
    /** @var array<string, string> */
    private array $cards = [];

    public function get(string $fingerprint): ?string
    {
        return $this->cards[Fingerprint::check($fingerprint)] ?? null;
    }

    public function put(string $fingerprint, string $png): void
    {
        $this->cards[Fingerprint::check($fingerprint)] = $png;
    }

    public function reset(): void
    {
        $this->cards = [];
    }
}
