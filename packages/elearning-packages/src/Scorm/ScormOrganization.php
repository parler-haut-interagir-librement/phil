<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

/**
 * An organization of a SCORM manifest: a titled tree of items, and its SCORM 2004 sequencing, kept for information.
 */
final readonly class ScormOrganization
{
    /**
     * @param list<ScormItem> $items
     */
    public function __construct(
        public string $identifier,
        public string $title,
        public array $items,
        public ?string $sequencing = null,
    ) {
    }
}
