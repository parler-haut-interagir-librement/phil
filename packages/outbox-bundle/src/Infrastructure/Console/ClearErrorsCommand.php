<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Doctrine\DBAL\Exception;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the messages from the errors the outbox table keeps, and keeps their class. An error keeps the class and
 * code of an exception, and the message of the outbox's own exceptions only; the errors written with the message of
 * any exception, which can quote personal data such as an address a mail server refused, are rewritten by this
 * command. Running it again rewrites nothing.
 */
#[AsCommand(name: 'phil:outbox:errors:clear', description: 'Removes the messages from the errors kept on the outbox records')]
final readonly class ClearErrorsCommand
{
    public function __construct(
        private DbalOutboxStore $store,
    ) {
    }

    /**
     * @throws Exception when the database fails
     */
    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d errors cleared of their message.', $this->store->clearErrorMessages()));

        return Command::SUCCESS;
    }
}
