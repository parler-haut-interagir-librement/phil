<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:courses:import`: imports a SCORM 1.2, SCORM 2004 or cmi5 package, or a cmi5 course structure alone, and says
 * what it created and what it left out.
 */
#[AsCommand(name: 'phil:courses:import', description: 'Imports a SCORM or cmi5 package as a course.')]
final readonly class ImportCommand
{
    public function __construct(
        private CourseImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The ZIP package, or a cmi5.xml whose units have absolute URLs.')]
        string $path,
        #[Option("The course's title, instead of the package's.")]
        ?string $title = null,
    ): int {
        try {
            $report = $this->importer->import($path, $title);
        } catch (PackageRefused $packageRefused) {
            $io->error($packageRefused->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Course %s imported from %s: %d modules, %d blocks, %d activities, %d files.', $report->course->value, $report->format->value, $report->modules, $report->blocks, $report->activities, $report->files));

        if ([] !== $report->warnings) {
            $io->listing($report->warnings);
        }

        return Command::SUCCESS;
    }
}
