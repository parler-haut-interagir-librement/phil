<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Benchmark;

use Phil\ElearningPackages\Archive\ZipPackage;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Tests\Fixture\Packages;

/**
 * What a platform pays to check a package at upload: reading a SCORM manifest and a cmi5 course structure, a manifest
 * of a thousand items, an archive of a thousand entries checked before any file is read, and a whole package read with
 * its launch files checked. Each must grow with the size of the package, never with its square. And what a commit of
 * the run time costs: a typical commit of SCORM 2004, and the data of an attempt of 250 interactions loaded, given one
 * more interaction and ended.
 */
final readonly class ElearningPackagesBench
{
    private string $manifest;

    private string $archive;

    private string $package;

    /** @var array<string, string|int> */
    private array $commit;

    /** @var array<string, string> */
    private array $attempt;

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
        $this->commit = [
            'cmi.location' => 'page-12', 'cmi.suspend_data' => str_repeat('s', 4_000), 'cmi.completion_status' => 'incomplete',
            'cmi.progress_measure' => '0.4', 'cmi.score.raw' => 8, 'cmi.score.max' => 10, 'cmi.score.min' => 0, 'cmi.score.scaled' => '0.8',
            'cmi.interactions.0.id' => 'urn:example:q1', 'cmi.interactions.0.type' => 'choice', 'cmi.interactions.0.learner_response' => 'a[,]c',
            'cmi.interactions.0.result' => 'correct', 'cmi.interactions.0.latency' => 'PT12S', 'cmi.session_time' => 'PT10M5S', 'cmi.exit' => 'suspend',
        ];
        $attempt = [];

        for ($i = 0; $i < 250; ++$i) {
            $attempt['cmi.interactions.'.$i.'.id'] = 'urn:example:q'.$i;
            $attempt['cmi.interactions.'.$i.'.type'] = 'choice';
            $attempt['cmi.interactions.'.$i.'.learner_response'] = 'a[,]b';
            $attempt['cmi.interactions.'.$i.'.result'] = 'correct';
        }

        $this->attempt = $attempt;
    }

    public function benchApplyACommitOfScorm2004(): void
    {
        CmiCommit::apply(new CmiData(ScormVersion::Scorm2004), $this->commit);
    }

    public function benchEndASessionOfAThousandValues(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004, $this->attempt);
        $data->set('cmi.objectives.0.id', 'o1');
        CmiAttempt::end($data);
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
