<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;
use Stringable;

/**
 * Where a step sits in a definition: its index among the top steps, then, inside each branch it is in, the path
 * taken and its index there. Written `2/then/0`, so that a run keeps it as text.
 */
final readonly class StepPath implements Stringable
{
    public const int MAX_DEPTH = 16;

    /**
     * @param list<array{0: BranchPath, 1: int}> $nested the path taken and the index, for each branch the step is in
     */
    private function __construct(
        public int $index,
        public array $nested = [],
    ) {
    }

    public static function top(int $index): self
    {
        return new self(max(0, $index));
    }

    /**
     * @throws InvalidDefinition when the text is not a path
     */
    public static function fromString(string $path): self
    {
        if (\strlen($path) > 255 || 1 !== preg_match('#^(0|[1-9][0-9]{0,3})((/(then|else)/(0|[1-9][0-9]{0,3})){0,16})$#D', $path, $matches)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a step path.', $path));
        }

        $parts = '' === $matches[2] ? [] : explode('/', substr($matches[2], 1));
        $nested = [];

        for ($i = 0, $count = \count($parts); $i < $count; $i += 2) {
            $nested[] = [BranchPath::from($parts[$i]), (int) $parts[$i + 1]];
        }

        return new self((int) $matches[1], $nested);
    }

    /**
     * The first step of a path of the branch this path points at.
     */
    public function into(BranchPath $path): self
    {
        return new self($this->index, [...$this->nested, [$path, 0]]);
    }

    /**
     * The step after this one in the same list.
     */
    public function sibling(): self
    {
        if ([] === $this->nested) {
            return new self($this->index + 1);
        }

        $nested = $this->nested;
        $last = array_pop($nested);
        $nested[] = [$last[0], $last[1] + 1];

        return new self($this->index, $nested);
    }

    /**
     * The branch this step sits in; `null` for a top step.
     */
    public function parent(): ?self
    {
        if ([] === $this->nested) {
            return null;
        }

        return new self($this->index, \array_slice($this->nested, 0, -1));
    }

    public function toString(): string
    {
        $path = (string) $this->index;

        foreach ($this->nested as [$branch, $index]) {
            $path .= '/'.$branch->value.'/'.$index;
        }

        return $path;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
