<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Cmi5\Cmi5Au;
use Phil\ElearningPackages\Cmi5\Cmi5Block;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Scorm\ScormItem;
use Phil\ElearningPackages\Scorm\ScormManifest;
use Phil\ElearningPackages\Scorm\ScormManifestReader;

/**
 * Reads a package whatever its format: a cmi5 package when `cmi5.xml` is at its root, else a SCORM package when
 * `imsmanifest.xml` is, its version read from the manifest. The archive and the manifest are read within the limits,
 * and what the manifest names but the package does not hold is noted: a launch file or a declared file missing, a
 * resource an item names that the manifest does not describe. Nothing is extracted and nothing is run.
 */
final readonly class PackageReader
{
    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
        private ?string $language = null,
        private PackageOpener $opener = new ZipPackageOpener(),
    ) {
    }

    /**
     * Opens the package at that path, reads it, and closes it.
     *
     * @throws PackageRefused when the file is not a package, is past a limit, holds an unsafe entry, or its manifest is
     *                        unsafe, malformed or describes no course
     */
    public function readFile(string $path): Package
    {
        $archive = $this->opener->open($path, $this->limits);

        try {
            return $this->read($archive);
        } finally {
            $archive->close();
        }
    }

    /**
     * Reads a package already opened; the caller closes it.
     *
     * @throws PackageRefused when the package holds no manifest at its root, or its manifest is unsafe, malformed or
     *                        describes no course
     */
    public function read(PackageArchive $archive): Package
    {
        $warnings = new Warnings();

        if ($archive->has(Cmi5CourseReader::FILE)) {
            $course = new Cmi5CourseReader($this->limits, $this->language)->read($archive->read(Cmi5CourseReader::FILE));
            $this->units($course->children, $archive, $warnings);

            return new Package(PackageFormat::Cmi5, $course, $archive->files(), $warnings->all());
        }

        if ($archive->has(ScormManifestReader::FILE)) {
            $manifest = new ScormManifestReader($this->limits)->read($archive->read(ScormManifestReader::FILE));
            $this->scorm($manifest, $archive, $warnings);

            return new Package($manifest->format, $manifest, $archive->files(), $warnings->all());
        }

        throw PackageRefused::because(\sprintf('The package holds neither %s nor %s at its root.', Cmi5CourseReader::FILE, ScormManifestReader::FILE));
    }

    /**
     * Reads a cmi5 course structure alone, as a platform receives one whose units are served from elsewhere: a unit
     * that names a file rather than an absolute URL is noted, since no package holds it.
     *
     * @throws PackageRefused when the structure is unsafe, malformed, or holds a value cmi5 does not allow
     */
    public function readStructure(string $xml): Package
    {
        $course = new Cmi5CourseReader($this->limits, $this->language)->read($xml);
        $warnings = new Warnings();
        $this->units($course->children, null, $warnings);

        return new Package(PackageFormat::Cmi5, $course, [], $warnings->all());
    }

    private function scorm(ScormManifest $manifest, PackageArchive $archive, Warnings $warnings): void
    {
        foreach ($manifest->organizations as $organization) {
            $this->items($manifest, $organization->items, $archive, $warnings);
        }

        foreach ($manifest->resources as $resource) {
            foreach ($resource->files as $file) {
                $path = $this->path($file);

                if (!$archive->has($path)) {
                    $warnings->add(\sprintf('The file "%s" of the resource "%s" is not in the package.', $path, $resource->identifier));
                }
            }
        }
    }

    /**
     * @param list<ScormItem> $items
     */
    private function items(ScormManifest $manifest, array $items, PackageArchive $archive, Warnings $warnings): void
    {
        foreach ($items as $item) {
            $this->items($manifest, $item->children, $archive, $warnings);

            if (null === $item->resource) {
                continue;
            }

            $resource = $manifest->resources[$item->resource] ?? null;

            if (null === $resource) {
                $warnings->add(\sprintf('The item "%s" names the resource "%s", which the manifest does not describe.', $item->identifier, mb_substr($item->resource, 0, 120)));

                continue;
            }

            if (null === $resource->href || PackagePath::isWebAddress($resource->href)) {
                continue;
            }

            $path = $this->path($resource->href);

            if (!$archive->has($path)) {
                $warnings->add(\sprintf('The launch file "%s" of the item "%s" is not in the package.', $path, $item->identifier));
            }
        }
    }

    /**
     * @param list<Cmi5Block|Cmi5Au> $children
     */
    private function units(array $children, ?PackageArchive $archive, Warnings $warnings): void
    {
        foreach ($children as $child) {
            if ($child instanceof Cmi5Block) {
                $this->units($child->children, $archive, $warnings);

                continue;
            }

            if (PackagePath::isWebAddress($child->url)) {
                continue;
            }

            if (!$archive instanceof PackageArchive) {
                $warnings->add(\sprintf('The assignable unit "%s" names a file, "%s", of a package that is not given: a course structure alone names absolute URLs.', $child->id, mb_substr($child->url, 0, 120)));

                continue;
            }

            [$path] = PackagePath::resolve([], $child->url);

            if (!$archive->has($path)) {
                $warnings->add(\sprintf('The launch file "%s" of the assignable unit "%s" is not in the package.', $path, $child->id));
            }
        }
    }

    /**
     * The file a reference the reader already resolved names: what precedes its query or its fragment.
     */
    private function path(string $reference): string
    {
        return substr($reference, 0, strcspn($reference, '?#'));
    }
}
