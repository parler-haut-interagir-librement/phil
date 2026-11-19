<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Console;

use Override;
use Phil\CalendarBundle\Application\Calendar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:calendar:refresh-tags` gives each event the names of its tags in the taxonomy again, after tags were renamed
 * or merged there.
 */
#[AsCommand(name: 'phil:calendar:refresh-tags', description: 'Gives each event the names of its tags in the taxonomy again')]
final class RefreshTagsCommand extends Command
{
    public function __construct(
        private readonly Calendar $calendar,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d events had their tags renamed.', $this->calendar->refreshTags()));

        return Command::SUCCESS;
    }
}
