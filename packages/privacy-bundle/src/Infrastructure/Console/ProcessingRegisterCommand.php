<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\PrivacyBundle\Application\Register\ProcessingActivity;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\Register\RegisterEntry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:register --format=markdown > docs/register.md.
 *
 * The register of processing: its activities, drawn from the rules of the retention schedule that hold personal
 * information, and its fields, drawn from the `#[PersonalData]` of the configured classes and of every Doctrine entity.
 * Markdown to keep with the documents of the business, or JSON. With `--strict`, the command fails while an activity
 * states no purpose or location, or a field names a rule the schedule does not hold: a check for the CI.
 */
#[AsCommand(name: 'phil:privacy:register', description: 'Prints the register of processing drawn from the retention schedule and #[PersonalData]')]
final class ProcessingRegisterCommand extends Command
{
    /**
     * @param list<class-string> $classes
     */
    public function __construct(
        private readonly ProcessingRegister $register,
        private readonly array $classes = [],
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'markdown or json', 'markdown');
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Fails while the register has gaps');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');

        if ('markdown' !== $format && 'json' !== $format) {
            $output->writeln('The format is markdown or json.');

            return Command::INVALID;
        }

        $classes = $this->classes();
        $activities = $this->register->activities($classes);
        $entries = $this->register->entries($classes);
        $gaps = $this->register->gaps($classes);

        if ('json' === $format) {
            $output->writeln(json_encode([
                'activities' => array_map(static fn (ProcessingActivity $activity): array => $activity->toArray(), $activities),
                'fields' => array_map(static fn (RegisterEntry $entry): array => $entry->toArray(), $entries),
                'gaps' => $gaps,
            ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        } else {
            $this->markdown($output, $activities, $entries, $gaps);
        }

        if ([] !== $gaps && true === $input->getOption('strict')) {
            ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln(\sprintf('The register has %d gap(s).', \count($gaps)));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<ProcessingActivity> $activities
     * @param list<RegisterEntry>      $entries
     * @param list<string>             $gaps
     */
    private function markdown(OutputInterface $output, array $activities, array $entries, array $gaps): void
    {
        $output->writeln('## Processing activities');
        $output->writeln('');
        $output->writeln('| Rule | Store | Categories | Purpose | Recipients | Processors | Location | Period | Fields |');
        $output->writeln('|---|---|---|---|---|---|---|---|---|');

        foreach ($activities as $activity) {
            $row = $activity->toArray();
            $output->writeln('| '.implode(' | ', array_map($this->cell(...), [
                $row['rule'],
                $row['store'],
                implode(', ', $row['categories']),
                $row['purpose'],
                implode(', ', $row['recipients']),
                implode(', ', array_map(static fn (array $processor): string => $processor['name'].' ('.$processor['location'].')', $row['processors'])),
                $row['location'],
                $row['period'],
                implode(', ', $row['fields']),
            ])).' |');
        }

        $output->writeln('');
        $output->writeln('## Personal fields');
        $output->writeln('');
        $output->writeln('| Category | Class | Property | Retention | Store | Period | Mechanism | Erasure |');
        $output->writeln('|---|---|---|---|---|---|---|---|');

        foreach ($entries as $entry) {
            $output->writeln('| '.implode(' | ', array_map($this->cell(...), $entry->toArray())).' |');
        }

        if ([] !== $gaps) {
            $output->writeln('');
            $output->writeln('## Gaps');
            $output->writeln('');

            foreach ($gaps as $gap) {
                $output->writeln('- '.$gap);
            }
        }
    }

    /**
     * A table cell: a dash when empty, its pipes escaped and its lines joined, so that a purpose cannot break the table.
     */
    private function cell(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '—';
        }

        return str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], $value);
    }

    /**
     * @return list<class-string>
     */
    private function classes(): array
    {
        $classes = $this->classes;

        foreach ($this->doctrine?->getManagers() ?? [] as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $classes[] = $metadata->getName();
            }
        }

        return $classes;
    }
}
