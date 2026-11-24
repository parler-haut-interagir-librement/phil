<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Exception\JournalRefused;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\JournalEntry;
use Phil\PrivacyBundle\Domain\Incident\JournalKind;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:incident-journal 4f… --kind=third_party_communication --party="Card issuer" --details="Card numbers, to block them"
 *     bin/console phil:privacy:incident-journal 4f….
 *
 * Adds an entry to the journal of an incident — a communication to a third party, a notice put off for an
 * investigation and its end, a consultation of the person in charge of the protection of personal information
 * (sections 3.5 and 3.7 of chapter P-39.1) —, or, without `--kind`, prints its journal.
 */
#[AsCommand(name: 'phil:privacy:incident-journal', description: 'Adds to, or prints, the journal of a confidentiality incident')]
final class IncidentJournalCommand extends Command
{
    public function __construct(
        private readonly IncidentJournalRecorder $journal,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('incident', InputArgument::REQUIRED, 'The identifier of the incident')
            ->addOption('kind', null, InputOption::VALUE_REQUIRED, 'What to record: '.implode(', ', array_map(static fn (JournalKind $kind): string => $kind->value, JournalKind::cases())))
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'When it was done; now by default')
            ->addOption('party', null, InputOption::VALUE_REQUIRED, 'Who: the recipient of the communication, the body that investigates, or the person consulted')
            ->addOption('details', null, InputOption::VALUE_REQUIRED, 'What and why: the information communicated and the purpose, the investigation, or the opinion')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'For a deferral, until when it is expected to last');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('incident');
        $incident = \is_string($argument) ? $argument : '';
        $kind = $this->text($input, 'kind');

        try {
            if (null === $kind) {
                $this->print($output, $this->journal->entries($incident));

                return Command::SUCCESS;
            }

            $entry = $this->journal->record(
                $incident,
                JournalKind::tryFrom($kind) ?? throw InvalidPrivacyValue::because('--kind is one of '.implode(', ', array_map(static fn (JournalKind $kind): string => $kind->value, JournalKind::cases())).'.'),
                $this->moment($input, 'at') ?? $this->clock->now(),
                $this->text($input, 'party') ?? '',
                $this->text($input, 'details') ?? '',
                $this->moment($input, 'until'),
            );
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            $output->writeln($invalidPrivacyValue->getMessage());

            return Command::INVALID;
        } catch (JournalRefused $journalRefused) {
            $output->writeln($journalRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Entry %d of the journal of incident %s recorded (section %s of chapter P-39.1).', $entry->sequence, $entry->incidentId, $entry->kind->section()));

        if (JournalKind::DeferralLifted === $entry->kind) {
            $output->writeln('The notice put off is now owed promptly: record it with phil:privacy:incident --subjects-notified.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<JournalEntry> $entries
     */
    private function print(OutputInterface $output, array $entries): void
    {
        if ([] === $entries) {
            $output->writeln('The journal of this incident is empty.');

            return;
        }

        foreach ($entries as $entry) {
            $output->writeln(\sprintf(
                '%d. %s, %s — %s%s%s',
                $entry->sequence,
                $entry->at->format(\DATE_ATOM),
                $entry->kind->value,
                $entry->party,
                '' === $entry->details ? '' : ': '.$entry->details,
                $entry->until instanceof DateTimeImmutable ? ' (until '.$entry->until->format(\DATE_ATOM).')' : '',
            ));
        }
    }

    private function moment(InputInterface $input, string $option): ?DateTimeImmutable
    {
        $value = $this->text($input, $option);

        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $this->clock->now()->getTimezone());
        } catch (Exception) {
            throw InvalidPrivacyValue::because(\sprintf('--%s is a date, such as 2026-11-10 or "2026-11-10 14:30".', $option));
        }
    }

    private function text(InputInterface $input, string $option): ?string
    {
        $value = $input->getOption($option);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
