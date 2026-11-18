<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use Generator;
use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use SplFileObject;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * `phil:courses:import-results`: imports the results another platform exported for an imported course, from a CSV
 * file whose first line names the columns `learner`, `item`, `status`, and optionally `score` and `date`.
 */
#[AsCommand(name: 'phil:courses:import-results', description: 'Imports the results exported from another platform for an imported course.')]
final readonly class ImportResultsCommand
{
    public function __construct(
        private ResultImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the imported course.')]
        string $course,
        #[Argument('The CSV file of the results.')]
        string $file,
    ): int {
        try {
            $result = $this->importer->import(CourseId::of($course), $this->rows(new SplFileObject($file, 'r')));
        } catch (CoursesException $coursesException) {
            $io->error($coursesException->getMessage());

            return Command::FAILURE;
        } catch (Throwable $throwable) {
            $io->error(\sprintf('The file cannot be read: %s', $throwable->getMessage()));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d results imported.', $result['imported']));

        foreach ($result['refused'] as $line => $reason) {
            $io->warning(\sprintf('Line %d: %s', $line, $reason));
        }

        return [] === $result['refused'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return Generator<int, array{learner?: ?string, item?: ?string, status?: ?string, score?: ?string, date?: ?string}>
     */
    private function rows(SplFileObject $file): Generator
    {
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $columns = null;

        foreach ($file as $index => $values) {
            if (!\is_array($values)) {
                continue;
            }

            $values = array_map(static fn (mixed $value): string => \is_string($value) ? trim($value) : '', $values);

            if (null === $columns) {
                $columns = $values;

                continue;
            }

            $row = [];

            foreach (['learner', 'item', 'status', 'score', 'date'] as $name) {
                $position = array_search($name, $columns, true);
                $row[$name] = false === $position ? null : ($values[$position] ?? null);
            }

            yield $index + 1 => $row;
        }
    }
}
