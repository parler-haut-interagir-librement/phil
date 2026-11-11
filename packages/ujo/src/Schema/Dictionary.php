<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * An object of texts under names of one form, `[a-z][a-z0-9-]*` by default: at most `$maxEntries` of them, each name
 * up to 64 characters and each text up to `$maxLength`. The entries keep their order.
 */
final readonly class Dictionary implements Sized
{
    public function __construct(
        private int $maxEntries = 32,
        private int $maxLength = 1000,
        private string $key = '/^[a-z][a-z0-9-]{0,63}$/D',
    ) {
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidDocument
     */
    #[Override]
    public function normalize(mixed $value, string $path, Context $context): array
    {
        if (!\is_array($value) || [] !== $value && array_is_list($value)) {
            throw InvalidDocument::at($path, 'an object is expected');
        }

        if (\count($value) > $this->maxEntries) {
            throw InvalidDocument::at($path, \sprintf('more than %d entries', $this->maxEntries));
        }

        $entries = [];

        foreach ($value as $name => $text) {
            if (1 !== preg_match($this->key, (string) $name)) {
                throw InvalidDocument::at($path, \sprintf('"%s" is not a name of the expected form', $name));
            }

            if (!\is_string($text) || mb_strlen($text) > $this->maxLength) {
                throw InvalidDocument::at($path.'.'.$name, \sprintf('a text of at most %d characters is expected', $this->maxLength));
            }

            $entries[(string) $name] = $text;
        }

        return $entries;
    }

    #[Override]
    public function size(mixed $value): int
    {
        return \is_array($value) ? \count($value) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'entries';
    }
}
