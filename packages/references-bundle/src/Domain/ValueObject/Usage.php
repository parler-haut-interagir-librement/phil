<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Domain\ValueObject;

use Phil\ReferencesBundle\Domain\Exception\InvalidReference;
use Phil\Resource\ResourceReference;

/**
 * One place a resource is used: the source that points to it, where in that source — a field, a path into a JSON
 * document: `blocks.2.image` —, and the target.
 */
final readonly class Usage
{
    private function __construct(
        public ResourceReference $source,
        public string $field,
        public ResourceReference $target,
    ) {
    }

    public static function of(ResourceReference $source, string $field, ResourceReference $target): self
    {
        if (1 !== preg_match('/^[\w.\[\]:-]{1,255}$/uD', $field)) {
            throw InvalidReference::because(\sprintf('A field is 1 to 255 letters, digits, "_", ".", ":", "-" or brackets; "%s" is not.', $field));
        }

        return new self($source, $field, $target);
    }
}
