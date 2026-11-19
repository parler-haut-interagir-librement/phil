<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Console;

use Override;
use Phil\CalendarBundle\Application\View\AlmanacImport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:calendar:import-almanac 2027` makes the almanac's dates of a year events of the calendar, once.
 */
#[AsCommand(name: 'phil:calendar:import-almanac', description: "Makes the almanac's dates of a year events of the calendar")]
final class ImportAlmanacCommand extends Command
{
    public function __construct(
        private readonly AlmanacImport $import,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::REQUIRED, 'The year, 2027 for one')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only the dates with this tag; again for another');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $year = $input->getArgument('year');

        if (!\is_string($year) || 1 !== preg_match('/^\d{4}$/D', $year)) {
            $output->writeln('<error>The year is four digits: 2027.</error>');

            return Command::FAILURE;
        }

        /** @var list<string> $tags the option's values, one per --tag */
        $tags = $input->getOption('tag');
        [$made, $kept] = $this->import->import((int) $year, [] === $tags ? null : $tags);
        $output->writeln(\sprintf('%d events made from the almanac of %s; %d dates already had theirs.', $made, $year, $kept));

        return Command::SUCCESS;
    }
}
