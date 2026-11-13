<?php

declare(strict_types=1);

namespace Phil\Citations;

/**
 * The references cited together at one place of a text: one parenthesis, one note, one bracket.
 */
final readonly class Citation
{
    /** @var non-empty-list<Cite> */
    public array $cites;

    public function __construct(Cite $cite, Cite ...$more)
    {
        $this->cites = [$cite, ...array_values($more)];
    }

    public static function of(string $id, string ...$more): self
    {
        return new self(new Cite($id), ...array_map(static fn (string $other): Cite => new Cite($other), $more));
    }
}
