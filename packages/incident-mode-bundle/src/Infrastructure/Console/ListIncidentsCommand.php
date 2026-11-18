<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Console;

use DateTimeImmutable;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\Preset;
use Phil\IncidentModeBundle\Application\DurationInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:incident:list`: the incidents in force; with `--journal`, a page of every incident, the latest first; with
 * `--presets`, the presets an incident starts from, with their proposed and longest durations. The moments are in UTC.
 */
#[AsCommand(name: 'phil:incident:list', description: 'Lists the incidents in force, the journal or the presets.')]
final readonly class ListIncidentsCommand
{
    public const int MAX_LIMIT = 500;

    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Lists every incident, the latest first, instead of those in force.')]
        bool $journal = false,
        #[Option('Lists the presets instead.')]
        bool $presets = false,
        #[Option('How many incidents of the journal, at most 500.')]
        int $limit = IncidentMode::DEFAULT_JOURNAL_PAGE,
        #[Option('How many incidents of the journal to skip.')]
        int $offset = 0,
    ): int {
        if ($presets) {
            $io->table(['Preset', 'Suspends', 'Proposed', 'Longest'], array_map(static fn (Preset $preset): array => [
                $preset->name,
                implode(', ', $preset->restrictionNames()),
                DurationInput::format($preset->defaultDuration),
                DurationInput::format($preset->maximumDuration),
            ], $this->incidents->presets()->all()));

            return Command::SUCCESS;
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT || $offset < 0) {
            $io->error(\sprintf('The limit is from 1 to %d, and the offset is not negative.', self::MAX_LIMIT));

            return Command::INVALID;
        }

        $activations = $journal ? $this->incidents->journal($limit, $offset) : $this->incidents->inForceNow();
        $io->table(['Incident', 'Preset', 'Suspends', 'Started (UTC)', 'Ends (UTC)', 'Started by', 'Lifted (UTC)', 'Lifted by', 'How'], array_map(static fn (Activation $activation): array => [
            $activation->id,
            $activation->preset,
            implode(', ', $activation->restrictionNames()),
            Moments::utc($activation->startedAt),
            Moments::utc($activation->endsAt),
            $activation->startedBy->id ?? '',
            $activation->liftedAt instanceof DateTimeImmutable ? Moments::utc($activation->liftedAt) : '',
            $activation->liftedBy->id ?? '',
            $activation->liftReason->value ?? '',
        ], $activations));

        return Command::SUCCESS;
    }
}
