<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Console;

use Override;
use Phil\LegalNotices\Event\DocumentPublished;
use Phil\LegalNotices\LegalNotices;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dispatches `DocumentPublished` for each version published in the last days, so that a listener tells the members or
 * clears a cache. It keeps nothing: run on a schedule, its span of days is the time between two runs.
 */
#[AsCommand(name: 'phil:legal-notices:announce', description: 'Dispatches an event for each legal document version published lately')]
final class AnnounceCommand extends Command
{
    public const int MAX_DAYS = 366;

    public function __construct(
        private readonly LegalNotices $notices,
        private readonly EventDispatcherInterface $events,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'The days before now a publication is announced for, 1 to 366', '1');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $input->getOption('days');

        if (!\is_string($days) || 1 !== preg_match('/^[1-9]\d{0,2}$/D', $days) || $days > self::MAX_DAYS) {
            $io->error('--days goes from 1 to 366.');

            return Command::INVALID;
        }

        $now = $this->clock->now();

        foreach ($this->notices->publishedBetween($now->modify(\sprintf('-%s days', $days)), $now) as $version) {
            $this->events->dispatch(new DocumentPublished($version->type->value, $version->number));
            $io->writeln(\sprintf('%s v%d', $version->type->value, $version->number));
        }

        return Command::SUCCESS;
    }
}
