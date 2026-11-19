<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Console;

use Override;
use Phil\DoctrineBehaviours\VersionEraser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Takes the `#[PersonalData]` fields out of every frozen version that still holds them in plain: the versions written
 * before the personal fields were left out. A sealed value stays, and no version is marked redacted.
 */
#[AsCommand(name: 'phil:doctrine-behaviours:strip-versions', description: 'Takes the personal fields out of the frozen versions already written')]
final class StripVersionsCommand extends Command
{
    public function __construct(
        private readonly VersionEraser $eraser,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the versions that hold personal fields, and change nothing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = true === $input->getOption('dry-run');
        $count = $this->eraser->stripPersonalFields($dryRun);
        $output->writeln(\sprintf($dryRun ? '%d versions hold personal fields in plain.' : '%d versions no longer hold personal fields.', $count));

        return Command::SUCCESS;
    }
}
