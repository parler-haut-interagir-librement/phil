<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * A medium that exists, kept in one of the allowed collections, of one of the allowed media types. Patterns take `*`:
 * `covers-*`, `image/*`. An empty list allows any; a medium outside every collection passes only when `collections`
 * is empty, or holds `''`.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class MediaFile extends Constraint
{
    public string $missingMessage = 'This medium does not exist.';

    public string $collectionMessage = 'This medium is not in one of the collections {{ collections }}.';

    public string $typeMessage = 'This file is a {{ type }}; allowed: {{ types }}.';

    /**
     * @param list<string>      $collections the collection keys allowed, as patterns
     * @param list<string>      $mimeTypes   the media types allowed, as patterns
     * @param list<string>|null $groups
     */
    public function __construct(
        public array $collections = [],
        public array $mimeTypes = [],
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }

    #[Override]
    public function validatedBy(): string
    {
        return MediaFileValidator::class;
    }
}
