<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:remind [--limit=1000].
 *
 * Dispatches `CertificateExpiring` for each certificate that reached a reminder's threshold since its holder was last
 * reminded, as `ExpiryReminders` does; run it once a day. The application's listener sends the reminders.
 */
#[AsCommand(name: 'phil:certificates:remind', description: 'Dispatches the reminders of the certificates about to end')]
final class RemindCommand extends Command
{
    public function __construct(
        private readonly ExpiryReminders $reminders,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The most reminders dispatched by this run', '1000');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $limit */
        $limit = $input->getOption('limit');

        try {
            $sent = $this->reminders->remind(max(1, Numbers::integer('limit', $limit)));
        } catch (InvalidValue $invalidValue) {
            ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln($invalidValue->getMessage());

            return Command::INVALID;
        }

        $output->writeln(\sprintf('Dispatched %d %s.', $sent, 1 === $sent ? 'reminder' : 'reminders'));

        return Command::SUCCESS;
    }
}
