<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Scorm\ScormManifest;

/**
 * A package as `PackageReader` reads it: its format, its SCORM manifest or its cmi5 course structure, the files it
 * holds, and the notes on what it describes but does not hold — a launch file missing, a resource an item names but
 * the manifest does not describe.
 */
final readonly class Package
{
    /**
     * @param list<string> $files    the files of the package, directories left out; empty for a course structure alone
     * @param list<string> $warnings the notes of the reading, each said once, at most `Warnings::MAX` and one more
     */
    public function __construct(
        public PackageFormat $format,
        public ScormManifest|Cmi5Course $manifest,
        public array $files = [],
        public array $warnings = [],
    ) {
    }

    /**
     * The identifier of the package: the identifier of its SCORM manifest, or the IRI of its cmi5 course.
     */
    public function identifier(): string
    {
        return $this->manifest instanceof ScormManifest ? $this->manifest->identifier : $this->manifest->id;
    }

    /**
     * The title of the package: that of the default organization of its SCORM manifest, or that of its cmi5 course.
     */
    public function title(): string
    {
        return $this->manifest instanceof ScormManifest ? $this->manifest->organization()->title : $this->manifest->title;
    }
}
