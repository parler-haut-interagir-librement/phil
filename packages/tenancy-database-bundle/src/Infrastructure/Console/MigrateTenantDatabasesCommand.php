<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Console;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationResult;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationStop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;

/**
 *     bin/console phil:tenancy:database:migrate [--only=shared] [--only=tenant_retail] [--concurrency=8].
 *
 * Migrates the shared database and every dedicated database, each in a process of its own, a bounded number at a
 * time. A database that fails is suspended — its tenants are answered with a `503` — and the others carry on; the
 * command fails while one did, and running it again resumes each database that then succeeds.
 *
 * Stopped by SIGINT or SIGTERM, it stops the running migrations — SIGTERM, the grace period, then SIGKILL — and waits
 * for them, lists the databases it did not migrate, and ends with 128 plus the signal's number. A second signal kills
 * the migrations at once.
 */
#[AsCommand(name: 'phil:tenancy:database:migrate', description: 'Migrates the shared database and each dedicated database, a bounded number at a time')]
final class MigrateTenantDatabasesCommand extends Command implements SignalableCommandInterface
{
    private const int SIGINT = 2;

    private const int SIGTERM = 15;

    private ?MigrationStop $stop = null;

    public function __construct(
        private readonly MigrationTargets $targets,
        private readonly MigrationRunner $runner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '"shared", or a dedicated database, to migrate those alone')
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, \sprintf('The migrations at a time, 1 to %d; the configured number by default', MigrationRunner::MAX_CONCURRENCY));
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $only */
        $only = $input->getOption('only');
        $concurrency = $input->getOption('concurrency');
        $concurrency = \is_string($concurrency) ? $this->integer($concurrency) : null;

        $targets = $this->targets->targets($only);
        $failed = 0;
        /** @var list<string> $notMigrated */
        $notMigrated = [];
        $this->stop = new MigrationStop();
        $stop = $this->stop;

        try {
            $this->runner->run($targets, $concurrency, static function (MigrationResult $result) use ($output, &$failed, &$notMigrated): void {
                $name = $result->target->database->toString();

                if ($result->succeeded()) {
                    $output->writeln(\sprintf('<info>✓</info> %s', $name));

                    return;
                }

                ++$failed;

                if ($result->interrupted) {
                    $notMigrated[] = $name;
                    $output->writeln($result->started ? \sprintf('<error>✗</error> %s was stopped before it finished; its tenants are suspended.', $name) : \sprintf('<comment>-</comment> %s was not started.', $name));

                    return;
                }

                $output->writeln(\sprintf('<error>✗</error> %s %s; its tenants are suspended.', $name, $result->timedOut ? 'ran past its timeout' : \sprintf('failed with exit code %d', $result->exitCode ?? -1)));

                if ('' !== $result->output) {
                    $output->writeln($result->output, OutputInterface::OUTPUT_RAW);
                }
            }, $stop);
        } finally {
            $this->stop = null;
        }

        $output->writeln(\sprintf('%d of %d databases migrated.', \count($targets) - $failed, \count($targets)));
        $signal = $stop->signal();

        if (null !== $signal) {
            $output->writeln(\sprintf('<error>Stopped by signal %d; not migrated: %s.</error>', $signal, implode(', ', $notMigrated)));

            return 128 + $signal;
        }

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * SIGINT and SIGTERM, when the `pcntl` extension lets the console catch them.
     *
     * @return list<int>
     */
    #[Override]
    public function getSubscribedSignals(): array
    {
        return SignalRegistry::isSupported() ? [self::SIGINT, self::SIGTERM] : [];
    }

    /**
     * While the migrations run, asks the runner to stop them and carries on, so that the command waits for them and
     * reports; otherwise leaves the signal to the console.
     */
    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (!$this->stop instanceof MigrationStop) {
            return $previousExitCode;
        }

        $this->stop->request($signal);

        return false;
    }

    private function integer(string $value): int
    {
        return 1 === preg_match('/^[1-9]\d{0,3}$/D', $value) ? (int) $value : 0;
    }
}
