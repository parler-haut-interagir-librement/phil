<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Console;

use Override;
use Phil\Shifts\Exception\ShiftsException;
use Phil\Shifts\Format\ShiftPlan;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Rosters;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports the shifts of a plan in the format `phil-shifts/1`: each new shift is planned with an empty roster; a shift
 * that exists is left as it is, or corrected with `--update`, keeping its volunteers.
 */
#[AsCommand(name: 'phil:shifts:import', description: 'Imports the shifts of a plan in the format phil-shifts/1')]
final class ImportPlanCommand extends Command
{
    public function __construct(
        private readonly Rosters $rosters,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The JSON file of the plan, 1 MiB at most')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Corrects the shifts that exist, keeping their volunteers');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $json = \is_string($file) && is_file($file) ? @file_get_contents($file, false, null, 0, ShiftPlan::MAX_BYTES + 1) : false;

        if (false === $json) {
            $io->error('The file cannot be read.');

            return Command::FAILURE;
        }

        try {
            $plan = ShiftPlan::fromJson($json);
        } catch (ShiftsException $shiftsException) {
            $io->error($shiftsException->getMessage());

            return Command::FAILURE;
        }

        $planned = 0;
        $corrected = 0;
        $kept = 0;

        foreach ($plan->shifts as $shift) {
            try {
                $this->rosters->plan($shift);
                ++$planned;
            } catch (RequestRefused) {
                if (true !== $input->getOption('update')) {
                    ++$kept;

                    continue;
                }

                try {
                    $this->rosters->correct($shift);
                    ++$corrected;
                } catch (ShiftsException $shiftsException) {
                    $io->warning(\sprintf('%s: %s', $shift->id, $shiftsException->getMessage()));
                    ++$kept;
                }
            }
        }

        $io->success(\sprintf('%d planned, %d corrected, %d kept as they were.', $planned, $corrected, $kept));

        return Command::SUCCESS;
    }
}
