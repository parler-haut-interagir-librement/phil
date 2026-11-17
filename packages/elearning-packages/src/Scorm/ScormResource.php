<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

/**
 * A resource of a SCORM manifest: a SCO, which talks to a runtime, or an asset, which does not; its launch file,
 * relative to the package once its `xml:base` is applied, or an absolute URL; its files and the resources it depends
 * on.
 */
final readonly class ScormResource
{
    /**
     * @param list<string> $files        the files the resource declares, relative to the package
     * @param list<string> $dependencies the identifiers of the resources it depends on
     */
    public function __construct(
        public string $identifier,
        public string $scormType,
        public ?string $href,
        public array $files = [],
        public array $dependencies = [],
    ) {
    }

    public function isSco(): bool
    {
        return 'sco' === $this->scormType;
    }
}
