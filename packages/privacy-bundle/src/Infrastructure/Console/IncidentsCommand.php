<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Incident\Incident;
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
 * authority that asks for it, or JSON.
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

        $output->writeln('| Identifier | Discovered | Occurred | Summary | Categories | Affected | Serious risk | Measures | Authority notified | People notified | Closed |');
        $output->writeln('|---|---|---|---|---|---|---|---|---|---|---|');

        foreach ($rows as $row) {
            $output->writeln('| '.implode(' | ', array_map($this->cell(...), [
                $row['id'],
                $row['discovered_at'],
                $row['occurred_at'],
                $row['summary'],
                implode(', ', $row['categories']),
                $row['affected'],
                $row['serious_risk'] ? 'yes' : 'no',
                $row['measures'],
                $row['authority_notified_at'],
                $row['subjects_notified_at'],
                $row['closed_at'],
            ])).' |');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{id: string, discovered_at: string, occurred_at: ?string, summary: string, categories: list<string>, affected: ?int, serious_risk: bool, measures: string, authority_notified_at: ?string, subjects_notified_at: ?string, closed_at: ?string, pending_notifications: list<string>}
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
        ];
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
