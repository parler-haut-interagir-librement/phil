<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

use Phil\ElearningPackages\PackageFormat;

/**
 * A SCORM manifest, `imsmanifest.xml`, as the reader reads it: its identifier, its version and edition, its
 * organizations with the default one, its resources by identifier, and the sequencing collection of SCORM 2004, kept
 * for information.
 */
final readonly class ScormManifest
{
    /**
     * @param list<ScormOrganization>      $organizations
     * @param array<string, ScormResource> $resources
     */
    public function __construct(
        public string $identifier,
        public PackageFormat $format,
        public string $schemaVersion,
        public array $organizations,
        public array $resources,
        public ?string $defaultOrganization = null,
        public ?string $sequencingCollection = null,
    ) {
    }

    /**
     * The organization the manifest names as its default, else its first.
     */
    public function organization(): ScormOrganization
    {
        return array_find($this->organizations, fn (ScormOrganization $organization): bool => $organization->identifier === $this->defaultOrganization) ?? $this->organizations[0];
    }
}
