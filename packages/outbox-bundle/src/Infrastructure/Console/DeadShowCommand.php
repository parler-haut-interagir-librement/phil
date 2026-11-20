<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the dead records, the oldest first, with the count of the records by state; or shows one of them, its payload
 * and its last error included.
 */
#[AsCommand(name: 'phil:outbox:dead:show', description: 'Lists the dead records of the outbox, or shows one of them')]
final readonly class DeadShowCommand
{
    public function __construct(
        private Housekeeping $housekeeping,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of a record to show')]
        ?string $id = null,
        #[Option('The most records to list')]
        int $limit = 50,
        #[Option('The records to skip, the oldest first')]
        int $offset = 0,
    ): int {
        if (null !== $id) {
            return $this->show($io, $id);
        }

        $counts = $this->housekeeping->counts();
        $io->writeln(\sprintf('%d pending, %d delivered, %d skipped, %d dead.', $counts['pending'], $counts['delivered'], $counts['skipped'], $counts['dead']));
        $dead = $this->housekeeping->dead(max(1, $limit), max(0, $offset));

        if ([] === $dead) {
            $io->success('No dead record.');

            return Command::SUCCESS;
        }

        $io->table(['Identifier', 'Type', 'Ordering key', 'Attempts', 'Dead since', 'Last error'], array_map(static fn (OutboxRecord $record): array => [
            $record->id->value,
            $record->type,
            $record->orderingKey ?? '',
            \sprintf('%d', $record->attempts),
            $record->settledAt?->format('Y-m-d H:i:s') ?? '',
            mb_strimwidth($record->lastError ?? '', 0, 80, '…'),
        ], $dead));

        return Command::SUCCESS;
    }

    private function show(SymfonyStyle $io, string $id): int
    {
        try {
            $record = $this->housekeeping->find(RecordId::fromString($id));
        } catch (InvalidRecord $invalidRecord) {
            $io->error($invalidRecord->getMessage());

            return Command::FAILURE;
        }

        if (!$record instanceof OutboxRecord) {
            $io->error(\sprintf('No record %s.', $id));

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Identifier' => $record->id->value],
            ['Type' => $record->type],
            ['State' => $record->state->value],
            ['Payload' => $record->payloadJson],
            ['Ordering key' => $record->orderingKey ?? ''],
            ['Idempotency key' => $record->idempotencyKey ?? ''],
            ['Occurred at' => $record->occurredAt->format('Y-m-d H:i:s')],
            ['Attempts' => \sprintf('%d', $record->attempts)],
            ['Settled at' => $record->settledAt?->format('Y-m-d H:i:s') ?? ''],
            ['Last error' => $record->lastError ?? ''],
        );

        return Command::SUCCESS;
    }
}
