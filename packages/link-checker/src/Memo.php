<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

/**
 * What one batch has already found, by kind and key, so that an address named by several links or contents is checked
 * once. Past its size, the oldest entry of a kind is forgotten.
 *
 * @internal
 */
final class Memo
{
    /** @var array<string, array<array-key, mixed>> */
    private array $entries = [];

    public function __construct(
        private readonly int $size,
    ) {
    }

    /**
     * What was found under the key, or what `$find` finds, kept.
     *
     * @template T
     *
     * @param callable(): T $find
     *
     * @return T
     */
    public function remember(string $kind, string $key, callable $find): mixed
    {
        $entries = $this->entries[$kind] ?? [];

        if (\array_key_exists($key, $entries)) {
            return $entries[$key];
        }

        $found = $find();

        if (\count($entries) >= $this->size) {
            foreach (array_keys($entries) as $oldest) {
                unset($entries[$oldest]);

                break;
            }
        }

        $entries[$key] = $found;
        $this->entries[$kind] = $entries;

        return $found;
    }
}
