<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Benchmark;

use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Tests\Support\MemoryTarget;

/**
 * The hot paths of an import of 1,000 people and of a tree of 1,000 units written children first: the file read,
 * the dry run, and the plan written through a target in memory.
 */
final readonly class PlanBench
{
    private string $people;

    private string $units;

    private ImportProfile $peopleProfile;

    private ImportProfile $unitsProfile;

    public function __construct()
    {
        $people = ["Email,First name,Last name,Matricule,Unit\r\n"];
        $units = ["Code,Name,Parent\r\n"];

        for ($i = 1; $i <= 1_000; ++$i) {
            $people[] = 'person'.$i.'@example.org,"Given '.$i.'",Family'.$i.',E-'.$i.",u1\r\n";
            $units[] = 'u'.(1_001 - $i).',Unit,'.(1_000 === $i ? '' : 'u'.(1_000 - $i))."\r\n";
        }

        $this->people = implode('', $people);
        $this->units = implode('', $units);
        $this->peopleProfile = LmsProfiles::people();
        $this->unitsProfile = LmsProfiles::organisationalUnits();
    }

    public function benchReadPeople(): void
    {
        CsvRowSource::fromString($this->people);
    }

    public function benchPlanPeople(): void
    {
        new ImportPlanner()->plan(CsvRowSource::fromString($this->people), $this->peopleProfile, new MemoryTarget('person', ['organisational-unit' => ['["u1"]']]));
    }

    public function benchPlanAndWriteUnitTree(): void
    {
        $target = new MemoryTarget('organisational-unit');
        new Importer()->commit(new ImportPlanner()->plan(CsvRowSource::fromString($this->units), $this->unitsProfile, $target), $target);
    }
}
