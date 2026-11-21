<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentHistory;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:incidents --format=markdown.
 *
 * The register of confidentiality incidents, the most recently discovered first: a Markdown table to hand to the
 * authority that asks for it, or JSON. Each line names the paragraphs of section 7 of the Québec regulation it still
 * lacks; a register that keeps its corrections (`IncidentHistory`) adds what each line held before them.
 */
#[AsCommand(name: 'phil:privacy:incidents', description: 'Prints the register of confidentiality incidents')]
final class IncidentsCommand extends Command
{
    public function __construct(
        private readonly IncidentRegister $register,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'markdown or json', 'markdown');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');

        if ('markdown' !== $format && 'json' !== $format) {
            $output->writeln('The format is markdown or json.');

            return Command::INVALID;
        }

        $rows = array_map($this->row(...), $this->register->all());

        if ('json' === $format) {
            $output->writeln(json_encode($rows, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $output->writeln('| Identifier | Discovered | Occurred | Summary | Categories | Affected | Serious risk | Measures | Authority notified | People notified | Closed | Discovered until | Occurred until | Approximate occurrence | Information | Undescribed because | Approximate affected | Risk assessment | Public notice | Public notice reason | Missing (s. 7) |');
        $output->writeln('|'.str_repeat('---|', 21));

        foreach ($rows as $row) {
            $output->writeln('| '.implode(' | ', array_map($this->cell(...), [
                $row['id'],
                $row['discovered_at'],
                $row['occurred_at'],
                $row['summary'],
                implode(', ', $row['categories']),
                $row['affected'],
                $this->yes($row['serious_risk']),
                $row['measures'],
                $row['authority_notified_at'],
                $row['subjects_notified_at'],
                $row['closed_at'],
                $row['discovered_until'],
                $row['occurred_until'],
                $this->yes($row['occurrence_approximate']),
                $row['information'],
                $row['undescribed_reason'],
                $this->yes($row['affected_approximate']),
                $row['risk_assessment'],
                $row['public_notice_at'],
                $row['public_notice_reason'],
                implode(', ', $row['missing_paragraphs']),
            ])).' |');
        }

        $corrections = array_merge(...array_map(static fn (array $row): array => $row['revisions'], $rows));

        if ([] !== $corrections) {
            $output->writeln('');
            $output->writeln('| Identifier | Correction | Field | Previous value | Corrected |');
            $output->writeln('|---|---|---|---|---|');

            foreach ($corrections as $revision) {
                $output->writeln('| '.implode(' | ', array_map($this->cell(...), [$revision['incident_id'], $revision['sequence'], $revision['field'], $revision['previous'], $revision['revised_at']])).' |');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{id: string, discovered_at: string, occurred_at: ?string, summary: string, categories: list<string>, affected: ?int, serious_risk: bool, measures: string, authority_notified_at: ?string, subjects_notified_at: ?string, closed_at: ?string, pending_notifications: list<string>, discovered_until: ?string, occurred_until: ?string, occurrence_approximate: bool, information: string, undescribed_reason: string, affected_approximate: bool, risk_assessment: string, public_notice_at: ?string, public_notice_reason: ?string, missing_paragraphs: list<int>, revisions: list<array{incident_id: string, sequence: int, field: string, previous: ?string, revised_at: string}>}
     */
    private function row(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'discovered_at' => $this->date($incident->discoveredAt) ?? '',
            'occurred_at' => $this->date($incident->occurredAt),
            'summary' => $incident->summary,
            'categories' => array_map(static fn (DataCategory $category): string => $category->value, $incident->categories),
            'affected' => $incident->affected,
            'serious_risk' => $incident->seriousRisk,
            'measures' => $incident->measures,
            'authority_notified_at' => $this->date($incident->authorityNotifiedAt),
            'subjects_notified_at' => $this->date($incident->subjectsNotifiedAt),
            'closed_at' => $this->date($incident->closedAt),
            'pending_notifications' => $incident->pendingNotifications(),
            'discovered_until' => $this->date($incident->discoveredUntil),
            'occurred_until' => $this->date($incident->occurredUntil),
            'occurrence_approximate' => $incident->occurrenceApproximate,
            'information' => $incident->information,
            'undescribed_reason' => $incident->undescribedReason,
            'affected_approximate' => $incident->affectedApproximate,
            'risk_assessment' => $incident->riskAssessment,
            'public_notice_at' => $this->date($incident->publicNoticeAt),
            'public_notice_reason' => $incident->publicNoticeReason?->value,
            'missing_paragraphs' => $incident->missingParagraphs(),
            'revisions' => $this->register instanceof IncidentHistory ? array_map(fn (IncidentRevision $revision): array => [
                'incident_id' => $revision->incidentId,
                'sequence' => $revision->sequence,
                'field' => $revision->field,
                'previous' => $revision->previous,
                'revised_at' => $this->date($revision->revisedAt) ?? '',
            ], $this->register->revisions($incident->id)) : [],
        ];
    }

    private function yes(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function date(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(\DATE_ATOM);
    }

    private function cell(int|string|null $value): string
    {
        if (null === $value || '' === $value) {
            return '—';
        }

        return str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], (string) $value);
    }
}
