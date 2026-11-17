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
 * Removes dead records for good, which lets the later records of their ordering keys go: the ones named, or the oldest
 * ones with `--all`. Nothing is removed without `--force`.
 */
#[AsCommand(name: 'phil:outbox:dead:remove', description: 'Removes dead records of the outbox for good, with --force')]
final readonly class DeadRemoveCommand
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
        #[Option('The most records --all removes')]
        int $limit = 100,
        #[Option('Remove them, which cannot be undone')]
        bool $force = false,
    ): int {
        if (!$all && [] === $ids) {
            $io->error('Name the dead records, or give --all.');

            return Command::INVALID;
        }

        if (!$force) {
            $io->note('Nothing removed: run again with --force to remove the records for good.');

            return Command::SUCCESS;
        }

        if ($all) {
            $io->success(\sprintf('%d records removed.', $this->housekeeping->discardAll(max(1, $limit))));

            return Command::SUCCESS;
        }

        $failed = 0;

        foreach ($ids as $id) {
            try {
                $removed = $this->housekeeping->discard(RecordId::fromString($id));
            } catch (InvalidRecord) {
                $removed = false;
            }

            if (!$removed) {
                $io->warning(\sprintf('%s is not a dead record.', $id));
                ++$failed;
            }
        }

        $io->success(\sprintf('%d records removed.', \count($ids) - $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
