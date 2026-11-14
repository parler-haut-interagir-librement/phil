<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Migration;

use InvalidArgumentException;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the migration command once per database, each in a process of its own, at most `concurrency` at a time.
 *
 * - **Failures are isolated.** A database whose migration fails, or runs past its timeout, is suspended: its tenants
 *   are answered with a `503` and their messages wait, while the other databases go on migrating and serving. A
 *   database that migrates successfully is resumed.
 * - **The tenant moves the connection.** A dedicated database is migrated with `--tenant=` one of its tenants, which
 *   `phil/tenancy-bundle` enters and this bundle follows; the shared database is migrated in no tenant.
 * - **What a process writes stays on the console.** It is kept, its last {@see self::OUTPUT_LIMIT} bytes, for the
 *   operator, and never logged: a migration that fails may print a connection error.
 * - **No process outlives the run.** A {@see MigrationStop} requested while the run waits — from the command's
 *   handler of SIGINT and SIGTERM — stops it: the running migrations are sent SIGTERM, given `gracePeriod` seconds,
 *   then killed, and waited for. Each gives an interrupted result and its database is suspended, as it may be
 *   half-migrated; each database not started yet gives a result that says so, and is left as it was. An exception
 *   stops the running migrations the same way before it goes on, and so does a fatal error, at shutdown.
 */
final readonly class MigrationRunner
{
    public const int MAX_CONCURRENCY = 32;

    public const int OUTPUT_LIMIT = 4096;

    private const int POLL_MICROSECONDS = 10_000;

    private const int SIGTERM = 15;

    /**
     * @param list<string> $command     the console and the command that migrates one database, such as
     *                                  `['bin/console', 'doctrine:migrations:migrate', '--no-interaction']`
     * @param ?float       $timeout     the seconds each migration may run, or `null` for no limit
     * @param ?string      $phpBinary   the PHP that runs the console; the one running now by default
     * @param float        $gracePeriod the seconds a migration stopped with the run is given after SIGTERM, before
     *                                  SIGKILL
     *
     * @throws InvalidArgumentException when the command is empty, or the concurrency, the timeout or the grace period
     *                                  is out of range
     */
    public function __construct(
        private array $command,
        private TenantDatabaseAvailability $availability,
        private int $concurrency = 4,
        private ?float $timeout = 3600.0,
        private ?string $phpBinary = null,
        private float $gracePeriod = 10.0,
    ) {
        if ([] === $command) {
            throw new InvalidArgumentException('The migration command cannot be empty.');
        }

        $this->checkConcurrency($concurrency);

        if (null !== $timeout && $timeout <= 0) {
            throw new InvalidArgumentException('The timeout of a migration is a positive number of seconds, or null.');
        }

        if ($gracePeriod < 0) {
            throw new InvalidArgumentException('The grace period of a stopped migration is a number of seconds, 0 or more.');
        }
    }

    /**
     * @param list<MigrationTarget>           $targets
     * @param ?int                            $concurrency the processes at a time for this run; the configured number by default
     * @param ?callable(MigrationResult):void $finished    told of each database as soon as it is done
     * @param ?MigrationStop                  $stop        requested, from a signal handler, to stop the run
     *
     * @return list<MigrationResult> in the order the databases finished; once stopped, the interrupted migrations,
     *                               then the databases not started
     */
    public function run(array $targets, ?int $concurrency = null, ?callable $finished = null, ?MigrationStop $stop = null): array
    {
        $concurrency ??= $this->concurrency;
        $this->checkConcurrency($concurrency);

        $pending = $targets;
        /** @var list<array{MigrationTarget, Process}> $running */
        $running = [];
        $results = [];
        $stop ??= new MigrationStop();
        // A fatal error runs neither `finally` nor the destructors of the processes: the shutdown stops them.
        register_shutdown_function(function () use (&$running): void {
            $this->terminate($running, null);
        });

        try {
            while (([] !== $pending || [] !== $running) && !$stop->isRequested()) {
                while ([] !== $pending && \count($running) < $concurrency) {
                    $target = array_shift($pending);
                    $process = $this->process($target);
                    $process->start();
                    $running[] = [$target, $process];
                }

                $still = [];

                foreach ($running as [$target, $process]) {
                    $result = $this->poll($target, $process);

                    if (!$result instanceof MigrationResult) {
                        $still[] = [$target, $process];

                        continue;
                    }

                    $this->finish($result, $results, $finished);
                }

                $running = $still;

                if ([] !== $running) {
                    usleep(self::POLL_MICROSECONDS);
                }
            }

            if ($stop->isRequested()) {
                $stopped = $running;
                $this->terminate($stopped, $stop);
                $running = [];

                foreach ($stopped as [$target, $process]) {
                    // A migration may end on its own while it is being stopped: what it says then stands.
                    $this->finish(0 === $process->getExitCode() ? new MigrationResult($target, 0, $this->tail($process)) : MigrationResult::interrupted($target, $this->tail($process)), $results, $finished);
                }

                foreach ($pending as $target) {
                    $this->finish(MigrationResult::notStarted($target), $results, $finished);
                }
            }
        } finally {
            $this->terminate($running, null);
            $running = [];
        }

        return $results;
    }

    /**
     * Suspends or resumes the database, unless its migration never started, and records the result.
     *
     * @param list<MigrationResult>           $results
     * @param ?callable(MigrationResult):void $finished
     */
    private function finish(MigrationResult $result, array &$results, ?callable $finished): void
    {
        if ($result->started) {
            $result->succeeded() ? $this->availability->resumeDatabase($result->target->database) : $this->availability->suspendDatabase($result->target->database);
        }

        $results[] = $result;

        if (null !== $finished) {
            $finished($result);
        }
    }

    /**
     * Sends SIGTERM to each running migration, waits for them up to the grace period — unless the stop is forced —,
     * then kills those still running, and waits for them.
     *
     * @param list<array{MigrationTarget, Process}> $running
     */
    private function terminate(array $running, ?MigrationStop $stop): void
    {
        foreach ($running as [, $process]) {
            try {
                $process->signal(self::SIGTERM);
            } catch (ProcessException) {
                // It has just ended on its own.
            }
        }

        $deadline = microtime(true) + $this->gracePeriod;

        while ([] !== array_filter($running, static fn (array $entry): bool => $entry[1]->isRunning()) && microtime(true) < $deadline && true !== $stop?->isForced()) {
            usleep(self::POLL_MICROSECONDS);
        }

        foreach ($running as [, $process]) {
            $process->stop(0);
        }
    }

    private function process(MigrationTarget $target): Process
    {
        $php = $this->phpBinary ?? new PhpExecutableFinder()->find(false);
        $command = [false === $php ? \PHP_BINARY : $php, ...$this->command];

        if ($target->tenant instanceof TenantId) {
            $command[] = \sprintf('--%s=%s', TenantOption::NAME, $target->tenant->value);
        }

        return new Process($command, timeout: $this->timeout);
    }

    private function poll(MigrationTarget $target, Process $process): ?MigrationResult
    {
        try {
            $process->checkTimeout();
        } catch (ProcessTimedOutException) {
            return new MigrationResult($target, null, $this->tail($process), true);
        }

        if ($process->isRunning()) {
            return null;
        }

        return new MigrationResult($target, $process->getExitCode(), $this->tail($process));
    }

    private function tail(Process $process): string
    {
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return \strlen($output) > self::OUTPUT_LIMIT ? '…'.substr($output, -self::OUTPUT_LIMIT) : $output;
    }

    private function checkConcurrency(int $concurrency): void
    {
        if ($concurrency < 1 || $concurrency > self::MAX_CONCURRENCY) {
            throw new InvalidArgumentException(\sprintf('The concurrency of the migrations is between 1 and %d; %d was given.', self::MAX_CONCURRENCY, $concurrency));
        }
    }
}
