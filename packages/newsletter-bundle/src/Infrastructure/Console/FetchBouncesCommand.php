<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Console;

use Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads the bounce mailbox now, as the schedule does every five minutes.
 */
#[AsCommand('phil:newsletter:fetch-bounces', 'Reads the bounce mailbox and applies its bounces')]
final readonly class FetchBouncesCommand
{
    public function __construct(
        private FetchBouncesHandler $handler,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The messages read at most')] int $limit = 100): int
    {
        $io->success(\sprintf('%d messages of the bounce mailbox were handled.', ($this->handler)(new FetchBounces($limit))));

        return Command::SUCCESS;
    }
}
