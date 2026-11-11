<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach;

use Phil\Passwords\Breach\Contract\BreachedPasswordSource;
use SensitiveParameter;

/**
 * Breached passwords held in memory by their SHA-1 hash: the application's own list, a list imported for a network
 * without access to the Internet, or the source of a test.
 */
final readonly class InMemoryBreachSource implements BreachedPasswordSource
{
    /**
     * @param array<string, int> $occurrences each uppercase SHA-1 hash to its occurrences
     */
    private function __construct(
        private array $occurrences,
    ) {
    }

    /**
     * @param array<array-key, int> $occurrences each password to its occurrences
     */
    public static function fromPasswords(#[SensitiveParameter] array $occurrences): self
    {
        $hashes = [];

        foreach ($occurrences as $password => $count) {
            $hashes[strtoupper(hash('sha1', (string) $password))] = $count;
        }

        return new self($hashes);
    }

    /**
     * @param array<string, int> $occurrences each SHA-1 hash, in hexadecimal and any case, to its occurrences
     */
    public static function fromHashes(array $occurrences): self
    {
        return new self(array_change_key_case($occurrences, \CASE_UPPER));
    }

    public function occurrences(#[SensitiveParameter] string $password): int
    {
        return $this->occurrences[strtoupper(hash('sha1', $password))] ?? 0;
    }
}
