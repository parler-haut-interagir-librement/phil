<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;

/**
 * What an index keeps of a resource: its identifier, the fields searched — `title`, `body` —, and the fields filtered
 * on — `locale`, `category` —, each a scalar or a list of them.
 */
final readonly class Document
{
    /**
     * @param array<string, string>              $text
     * @param array<string, scalar|list<scalar>> $filters
     */
    private function __construct(
        public string $index,
        public string $id,
        public array $text,
        public array $filters,
    ) {
    }

    /**
     * @param array<string, string>              $text
     * @param array<string, scalar|list<scalar>> $filters
     */
    public static function of(string $index, string $id, array $text, array $filters = []): self
    {
        Index::check($index);

        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $id)) {
            throw InvalidSearch::because(\sprintf('A document identifier is 1 to 255 letters, digits, "_" or "-"; "%s" is not.', $id));
        }

        foreach ([...array_keys($text), ...array_keys($filters)] as $field) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field)) {
                throw InvalidSearch::because(\sprintf('A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $field));
            }
        }

        return new self($index, $id, $text, $filters);
    }
}
