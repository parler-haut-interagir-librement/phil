<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

/**
 * A file listed in the manifest of the package document — its properties, its fallback and its media overlay by their
 * identifiers — and its place in the spine when it has one, with the properties of that place.
 *
 * @internal
 */
final readonly class ManifestItem
{
    /**
     * @param list<string> $properties
     * @param list<string> $spineProperties
     */
    public function __construct(
        public string $id,
        public string $href,
        public string $mediaType,
        public array $properties = [],
        public ?bool $linear = null,
        public ?string $fallback = null,
        public ?string $mediaOverlay = null,
        public array $spineProperties = [],
    ) {
    }
}
