<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Console;

use Override;
use Phil\Geo\Geocoding\MaxMind\Metadata;
use Phil\GeoBundle\Infrastructure\MaxMind\DatabaseUpdater;
use Phil\GeoBundle\Infrastructure\MaxMind\UpdateFailed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Brings the MaxMind database up to date, for a daily cron: it downloads nothing when the latest is already in place.
 */
#[AsCommand(name: 'phil:geo:maxmind:update', description: 'Downloads the latest MaxMind database and puts it in place')]
final class UpdateMaxMindDatabaseCommand extends Command
{
    public function __construct(
        private readonly DatabaseUpdater $updater,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Download it even when the latest is already in place');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $metadata = $this->updater->update(true === $input->getOption('force'));
        } catch (UpdateFailed $updateFailed) {
            $output->writeln(\sprintf('<error>%s</error>', $updateFailed->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln($metadata instanceof Metadata
            ? \sprintf('The MaxMind database %s built on %s is in place.', $metadata->databaseType, $metadata->builtAt->format('Y-m-d'))
            : 'The MaxMind database is already the latest.');

        return Command::SUCCESS;
    }
}
