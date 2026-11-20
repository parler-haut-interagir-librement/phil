<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Console;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:commerce-syndication:deliver --limit=100.
 *
 * Run by the scheduler every minute: a message waits its turn in the outbox, so a partner down for a while misses
 * nothing.
 */
#[AsCommand(name: 'phil:commerce-syndication:deliver', description: 'Delivers the messages due to the partners')]
final class DeliverMessagesCommand extends Command
{
    public function __construct(
        private readonly Courier $courier,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many messages to try at most', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');

        if (!\is_string($limit) || 1 !== preg_match('/^[1-9]\d{0,5}$/D', $limit)) {
            $output->writeln('<error>The limit is a whole number from 1 to 999999.</error>');

            return Command::INVALID;
        }

        $delivered = $this->courier->deliver($this->clock->now(), (int) $limit);
        $this->entityManager->flush();
        $output->writeln(\sprintf('%d message(s) delivered.', $delivered));

        return Command::SUCCESS;
    }
}
