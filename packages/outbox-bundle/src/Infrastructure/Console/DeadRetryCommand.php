<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\RecordId;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Puts dead records back to pending, to be relayed at once with their attempts back to 0: the ones named, or the
 * oldest ones with `--all`.
 */
#[AsCommand(name: 'phil:outbox:dead:retry', description: 'Puts dead records of the outbox back to pending')]
final readonly class DeadRetryCommand
{
    public function __construct(
        private Housekeeping $housekeeping,
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifiers of the dead records')]
        array $ids = [],
        #[Option('Every dead record, the oldest first, up to --limit')]
        bool $all = false,
        #[Option('The most records --all puts back')]
        int $limit = 100,
    ): int {
        if ($all) {
            $io->success(\sprintf('%d records back to pending.', $this->housekeeping->retryAll(max(1, $limit))));

            return Command::SUCCESS;
        }

        if ([] === $ids) {
            $io->error('Name the dead records, or give --all.');

            return Command::INVALID;
        }

        $failed = 0;

        foreach ($ids as $id) {
            try {
                $retried = $this->housekeeping->retry(RecordId::fromString($id));
            } catch (InvalidRecord) {
                $retried = false;
            }

            if (!$retried) {
                $io->warning(\sprintf('%s is not a dead record.', $id));
                ++$failed;
            }
        }

        $io->success(\sprintf('%d records back to pending.', \count($ids) - $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
