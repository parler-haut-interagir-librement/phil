<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * One text of a list.
 */
final readonly class Choice implements Field
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        public array $choices,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): string
    {
        if (!\is_string($value) || !\in_array($value, $this->choices, true)) {
            throw InvalidDocument::at($path, \sprintf('one of "%s" is expected', implode('", "', $this->choices)));
        }

        return $value;
    }
}
