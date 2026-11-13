<?php

declare(strict_types=1);

namespace Phil\Epub\Accessibility;

use Phil\Epub\Exception\InvalidBook;

/**
 * What the publisher declares about the accessibility of the publication, written as schema.org and EPUB
 * Accessibility metadata: the access modes, the sets of modes sufficient to read it, its features, its hazards, a
 * summary, the specification it conforms to and who certified it.
 *
 * The writer adds what it knows for itself: the `textual` mode, the `visual` mode when the book holds an image, the
 * `tableOfContents` feature, and `pageNavigation` when the book has a page list.
 */
final readonly class Accessibility
{
    /**
     * @param list<AccessMode>                 $accessModes
     * @param list<non-empty-list<AccessMode>> $sufficientModes
     * @param list<AccessibilityFeature>       $features
     * @param list<AccessibilityHazard>        $hazards
     *
     * @throws InvalidBook
     */
    public function __construct(
        public array $accessModes = [],
        public array $sufficientModes = [],
        public array $features = [],
        public array $hazards = [],
        public ?string $summary = null,
        public ?string $conformsTo = null,
        public ?string $certifiedBy = null,
    ) {
        foreach ([AccessibilityHazard::None, AccessibilityHazard::Unknown] as $alone) {
            if (\in_array($alone, $hazards, true) && 1 !== \count($hazards)) {
                throw InvalidBook::because(\sprintf('The hazard "%s" cannot be declared with another hazard.', $alone->value));
            }
        }

        if (\in_array(AccessibilityFeature::None, $features, true) && 1 !== \count($features)) {
            throw InvalidBook::because('The feature "none" cannot be declared with another feature.');
        }
    }
}
