<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Fixture;

use Override;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Key\KeySet;
use Phil\Url\Url;

/**
 * Key sets by address, counted as they are read; a refresh reads the next set given for the address, when there is one.
 */
final class StaticKeySets implements KeySetSource
{
    /** @var array<string, list<KeySet>> */
    private array $sets = [];

    /** @var list<array{string, bool}> */
    public array $reads = [];

    public function with(string $url, KeySet ...$sets): self
    {
        $this->sets[$url] = array_values($sets);

        return $this;
    }

    public function count(): int
    {
        return \count($this->reads);
    }

    #[Override]
    public function keySet(Url $url, bool $refresh = false): KeySet
    {
        $this->reads[] = [$url->toString(), $refresh];
        $sets = $this->sets[$url->toString()] ?? [];

        if ([] === $sets) {
            throw ServiceFailed::because('No key set at '.$url->toString());
        }

        return $refresh && \count($sets) > 1 ? $sets[1] : $sets[0];
    }
}
