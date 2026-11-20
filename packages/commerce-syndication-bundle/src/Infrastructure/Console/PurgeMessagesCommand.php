<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Console;

use Override;
use Phil\CommerceSyndicationBundle\Application\MessageRetention;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:commerce-syndication:purge.
 *
 * Run by the scheduler once a day: deletes the messages delivered, given up or received more than `retention_days`
 * ago. The deletions run at once, outside any unit of work.
 */
#[AsCommand(name: 'phil:commerce-syndication:purge', description: 'Deletes the messages past their retention')]
final class PurgeMessagesCommand extends Command
{
    public function __construct(
        private readonly MessageRetention $retention,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$outgoing, $received] = $this->retention->purge($this->clock->now());
        $output->writeln(\sprintf('%d outgoing message(s) and %d received id(s) deleted.', $outgoing, $received));

        return Command::SUCCESS;
    }
}
