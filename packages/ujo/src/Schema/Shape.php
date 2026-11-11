<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Validation;

/**
 * An object of named properties, normalized in the order the shape declares them. A key the shape does not declare is
 * refused, unless the shape is open, when it is kept as it is after the declared ones. At `Validation::Publication`, a
 * property declared `filled()` must be filled in.
 */
final readonly class Shape implements Field
{
    /**
     * @param array<string, Property> $properties
     */
    public function __construct(
        public array $properties = [],
        public bool $open = false,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidDocument
     */
    #[Override]
    public function normalize(mixed $value, string $path, Context $context): array
    {
        if (!\is_array($value) || [] !== $value && array_is_list($value)) {
            throw InvalidDocument::at($path, 'an object is expected');
        }

        $normalized = [];
        $publication = Validation::Publication === $context->level();

        foreach ($this->properties as $name => $property) {
            if (\array_key_exists($name, $value)) {
                $normalized[$name] = null === $value[$name] && $property->nullable ? null : $property->field->normalize($value[$name], $path.'.'.$name, $context);
            } elseif ($property->required) {
                throw InvalidDocument::at($path.'.'.$name, 'this value is required');
            } elseif ($property->hasDefault) {
                $normalized[$name] = $property->default;
            }

            if ($publication && $property->filled && !$property->isFilledBy($normalized[$name] ?? null)) {
                throw InvalidDocument::at($path.'.'.$name, 'this value must be filled in to publish');
            }
        }

        foreach (array_diff_key($value, $this->properties) as $name => $extra) {
            if (!$this->open) {
                throw InvalidDocument::at($path.'.'.$name, 'this value is not part of the data');
            }

            $normalized[$name] = $extra;
        }

        return $normalized;
    }
}
