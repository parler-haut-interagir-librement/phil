<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Benchmark;

use Phil\ElearningPackages\Archive\ZipPackage;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Tests\Fixture\Packages;

/**
 * What a platform pays to check a package at upload: reading a SCORM manifest and a cmi5 course structure, a manifest
 * of a thousand items, an archive of a thousand entries checked before any file is read, and a whole package read with
 * its launch files checked. Each must grow with the size of the package, never with its square.
 */
final readonly class ElearningPackagesBench
{
    private string $manifest;

    private string $archive;

    private string $package;

    public function __construct()
    {
        $items = implode('', array_map(static fn (int $i): string => \sprintf('<item identifier="i%1$d" identifierref="r%1$d"><title>Item %1$d</title></item>', $i), range(1, 1_000)));
        $resources = implode('', array_map(static fn (int $i): string => \sprintf('<resource identifier="r%1$d" adlcp:scormtype="sco" href="sco/%1$d/index.html"><file href="sco/%1$d/index.html"/></resource>', $i), range(1, 1_000)));
        $this->manifest = '<manifest identifier="m" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"><metadata><schemaversion>1.2</schemaversion></metadata>'
            .'<organizations><organization identifier="o"><title>O</title>'.$items.'</organization></organizations><resources>'.$resources.'</resources></manifest>';
        $this->archive = Packages::zip(array_combine(
            array_map(static fn (int $i): string => \sprintf('sco/%d/index.html', $i), range(1, 1_000)),
            array_fill(0, 1_000, '<h1>Page</h1>'),
        ), 'phil-elearning-packages-bench-entries.zip');
        $this->package = Packages::zip(Packages::scorm12Files(), 'phil-elearning-packages-bench-package.zip');
    }

    public function benchReadAScormManifest(): void
    {
        new ScormManifestReader()->read(Packages::SCORM12);
    }

    public function benchReadACmi5CourseStructure(): void
    {
        new Cmi5CourseReader()->read(Packages::CMI5);
    }

    public function benchReadAManifestOfAThousandItems(): void
    {
        new ScormManifestReader()->read($this->manifest);
    }

    public function benchCheckAnArchiveOfAThousandEntries(): void
    {
        ZipPackage::open($this->archive, new PackageLimits())->close();
    }

    public function benchReadAPackageWithItsLaunchFiles(): void
    {
        new PackageReader()->readFile($this->package);
    }
}
