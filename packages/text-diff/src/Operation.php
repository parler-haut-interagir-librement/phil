<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * A run of tokens that one difference keeps, deletes or inserts.
 */
final readonly class Operation
{
    /**
     * @param non-empty-list<string> $tokens
     */
    public function __construct(
        public OperationKind $kind,
        public array $tokens,
    ) {
    }

    /**
     * The tokens put end to end.
     */
    public function text(): string
    {
        return implode('', $this->tokens);
    }
}
