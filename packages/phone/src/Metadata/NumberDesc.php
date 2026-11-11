<?php

declare(strict_types=1);

namespace Phil\Phone\Metadata;

/**
 * The numbers of one type in a territory: their national pattern and their possible lengths.
 */
final readonly class NumberDesc
{
    /** The pattern anchored at both ends, built once. */
    private string $regex;

    /** @var array<int, true> by length */
    private array $byLength;

    /**
     * @param list<int> $lengths
     */
    public function __construct(
        public string $pattern,
        public array $lengths,
    ) {
        $this->regex = '/^(?:'.$pattern.')$/';
        $this->byLength = array_fill_keys($lengths, true);
    }

    public function matches(string $nationalNumber): bool
    {
        return isset($this->byLength[\strlen($nationalNumber)]) && 1 === preg_match($this->regex, $nationalNumber);
    }
}
