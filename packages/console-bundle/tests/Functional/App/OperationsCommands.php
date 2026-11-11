<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Functional\App;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Operations commands of an application, declared as methods of one service.
 */
final readonly class OperationsCommands
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[AsCommand('app:wipe', 'Wipes the data')]
    #[ForbidEnvironment('test', 'prod')]
    public function wipe(OutputInterface $output): int
    {
        $output->writeln('wiped');

        return 0;
    }

    #[AsCommand('app:anonymize', 'Anonymizes the customers')]
    #[ConfirmEnvironment('test')]
    public function anonymize(OutputInterface $output): int
    {
        $this->logger->debug('Anonymizing {count} customers', ['count' => 3]);
        $output->writeln('anonymized');

        return 0;
    }
}
