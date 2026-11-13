<?php

declare(strict_types=1);

namespace Phil\Stockroom\Memory;

use Override;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Place;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The balances in memory, for tests and short scripts; emptied by `reset()` between the requests of a worker.
 */
final class InMemoryBalances implements Balances, ResetInterface
{
    /** @var array<array-key, array<string, int>> the quantities by item, then by the key of their place */
    private array $quantities = [];

    #[Override]
    public function quantity(string $item, Place $place): int
    {
        return $this->quantities[$item][$place->key()] ?? 0;
    }

    #[Override]
    public function set(string $item, Place $place, int $quantity): void
    {
        if ($quantity > 0) {
            $this->quantities[$item][$place->key()] = $quantity;

            return;
        }

        unset($this->quantities[$item][$place->key()]);
    }

    #[Override]
    public function of(string $item): array
    {
        $places = $this->quantities[$item] ?? [];
        ksort($places, \SORT_STRING);
        $holdings = [];

        foreach ($places as $key => $quantity) {
            $holdings[] = new Holding($item, Place::fromKey($key), $quantity);
        }

        return $holdings;
    }

    #[Override]
    public function at(Place $place, int $limit): array
    {
        $key = $place->key();
        $items = array_keys($this->quantities);
        sort($items, \SORT_STRING);
        $holdings = [];

        foreach ($items as $item) {
            $item = (string) $item;

            if (\count($holdings) < $limit && isset($this->quantities[$item][$key])) {
                $holdings[] = new Holding($item, $place, $this->quantities[$item][$key]);
            }
        }

        return $holdings;
    }

    #[Override]
    public function reset(): void
    {
        $this->quantities = [];
    }
}
