<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:incident "Mailing list exported to a public folder" --category=email --affected=120 --serious-risk
 *     bin/console phil:privacy:incident --id=4f… --authority-notified=2026-11-12 --measures="Folder removed, link revoked".
 *
 * Records a confidentiality incident, or, with `--id`, completes or corrects one already recorded: the summary given
 * as the argument replaces the one it had, a value left out keeps it, and `--clear` empties a date or a number recorded
 * by mistake. A date left out is now for `--discovered` of a new incident. The command says which notifications a risk
 * of serious injury still asks for, and which paragraphs of section 7 of the Québec regulation the line still lacks.
 */
#[AsCommand(name: 'phil:privacy:incident', description: 'Records or completes a line of the register of confidentiality incidents')]
final class IncidentCommand extends Command
{
    /**
     * The paragraphs of section 7 a line can lack, as `Incident::missingParagraphs()` names them.
     */
    private const array PARAGRAPHS = [
        1 => 'the information concerned, or why it cannot be described: --category, --information or --undescribed-reason',
        3 => 'when it occurred: --occurred',
        5 => 'how many people: --affected',
        6 => 'the assessment of the risk: --risk-assessment',
        7 => 'the notifications still owed',
        8 => 'the measures: --measures',
    ];

    private const array CLEARABLE = ['occurred', 'occurred-until', 'discovered-until', 'affected', 'authority-notified', 'subjects-notified', 'public-notice', 'closed'];

    public function __construct(
        private readonly IncidentRegister $register,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('summary', InputArgument::OPTIONAL, 'The circumstances: what happened, required for a new incident, a correction with --id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'The incident to complete or correct')
            ->addOption('discovered', null, InputOption::VALUE_REQUIRED, 'When it was discovered, or the start of the period; now by default')
            ->addOption('discovered-until', null, InputOption::VALUE_REQUIRED, 'The end of the period of the discovery')
            ->addOption('occurred', null, InputOption::VALUE_REQUIRED, 'When it happened, or the start of the period, when known')
            ->addOption('occurred-until', null, InputOption::VALUE_REQUIRED, 'The end of the period of the incident')
            ->addOption('approximate-occurrence', null, InputOption::VALUE_NEGATABLE, 'Whether the date or period of the incident is an approximation')
            ->addOption('category', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A kind of personal information concerned')
            ->addOption('information', null, InputOption::VALUE_REQUIRED, 'A description of the personal information concerned')
            ->addOption('undescribed-reason', null, InputOption::VALUE_REQUIRED, 'Why the personal information concerned cannot be described')
            ->addOption('affected', null, InputOption::VALUE_REQUIRED, 'How many people are concerned')
            ->addOption('approximate-affected', null, InputOption::VALUE_NEGATABLE, 'Whether the number of people is an approximation')
            ->addOption('serious-risk', null, InputOption::VALUE_NEGATABLE, 'Whether it presents a risk of serious injury')
            ->addOption('risk-assessment', null, InputOption::VALUE_REQUIRED, 'What leads to conclude that there is, or is not, a risk of serious injury')
            ->addOption('measures', null, InputOption::VALUE_REQUIRED, 'What was done to reduce the risk and prevent another')
            ->addOption('authority-notified', null, InputOption::VALUE_REQUIRED, 'When the authority was notified')
            ->addOption('subjects-notified', null, InputOption::VALUE_REQUIRED, 'When the people concerned were notified')
            ->addOption('public-notice', null, InputOption::VALUE_REQUIRED, 'When a public notice was given, with --public-notice-reason')
            ->addOption('public-notice-reason', null, InputOption::VALUE_REQUIRED, 'Why: '.implode(', ', array_map(static fn (PublicNoticeReason $reason): string => $reason->value, PublicNoticeReason::cases())))
            ->addOption('closed', null, InputOption::VALUE_REQUIRED, 'When it was closed')
            ->addOption('clear', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A field recorded by mistake to empty: '.implode(', ', self::CLEARABLE));
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $incident = $this->incident($input);
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            $output->writeln($invalidPrivacyValue->getMessage());

            return Command::INVALID;
        }

        if (!$incident instanceof Incident) {
            $output->writeln('No incident has that identifier.');

            return Command::FAILURE;
        }

        $this->register->save($incident);
        $output->writeln(\sprintf('Incident %s recorded.', $incident->id));

        foreach ($incident->pendingNotifications() as $pending) {
            $output->writeln('authority' === $pending
                ? 'It presents a risk of serious injury: notify the authority promptly, then record it with --authority-notified.'
                : 'It presents a risk of serious injury: notify the people concerned promptly, then record it with --subjects-notified.');
        }

        $missing = $incident->missingParagraphs();

        if ([] !== $missing) {
            $output->writeln(\sprintf('Still to record for section 7 of the regulation: paragraph %s.', implode(', ', array_map(static fn (int $paragraph): string => \sprintf('%d (%s)', $paragraph, self::PARAGRAPHS[$paragraph]), $missing))));
        }

        return Command::SUCCESS;
    }

    private function incident(InputInterface $input): ?Incident
    {
        $categories = $this->categories($input);
        $affected = $this->affected($input);
        $id = $this->text($input, 'id');
        $summary = $input->getArgument('summary');
        $summary = \is_string($summary) ? $summary : null;

        if (null === $id) {
            if (null === $summary) {
                throw InvalidPrivacyValue::because('A new incident needs its summary; to complete one, give its --id.');
            }

            // What the options give, the discovery included, is applied below, as for a line being completed.
            $incident = Incident::record($summary, $this->clock->now(), null, [], null, false);
        } else {
            $incident = $this->register->find($id);

            if (!$incident instanceof Incident) {
                return null;
            }
        }

        $reason = $this->text($input, 'public-notice-reason');

        return $incident->update(
            measures: $this->text($input, 'measures'),
            seriousRisk: $this->flag($input, 'serious-risk'),
            affected: $affected,
            categories: $categories,
            authorityNotifiedAt: $this->moment($input, 'authority-notified'),
            subjectsNotifiedAt: $this->moment($input, 'subjects-notified'),
            closedAt: $this->moment($input, 'closed'),
            summary: $summary,
            discoveredAt: $this->moment($input, 'discovered'),
            discoveredUntil: $this->moment($input, 'discovered-until'),
            occurredAt: $this->moment($input, 'occurred'),
            occurredUntil: $this->moment($input, 'occurred-until'),
            occurrenceApproximate: $this->flag($input, 'approximate-occurrence'),
            affectedApproximate: $this->flag($input, 'approximate-affected'),
            information: $this->text($input, 'information'),
            undescribedReason: $this->text($input, 'undescribed-reason'),
            riskAssessment: $this->text($input, 'risk-assessment'),
            publicNoticeAt: $this->moment($input, 'public-notice'),
            publicNoticeReason: null === $reason ? null : PublicNoticeReason::tryFrom($reason) ?? throw InvalidPrivacyValue::because(\sprintf('--public-notice-reason is one of %s.', implode(', ', array_map(static fn (PublicNoticeReason $case): string => $case->value, PublicNoticeReason::cases())))),
            clear: $this->clear($input),
        );
    }

    private function flag(InputInterface $input, string $option): ?bool
    {
        $value = $input->getOption($option);

        return \is_bool($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function clear(InputInterface $input): array
    {
        $values = $input->getOption('clear');
        $fields = [];

        foreach (\is_array($values) ? $values : [] as $value) {
            if (!\in_array($value, self::CLEARABLE, true)) {
                throw InvalidPrivacyValue::because(\sprintf('--clear is one of %s.', implode(', ', self::CLEARABLE)));
            }

            $fields[] = str_replace('-', '_', $value);
        }

        return $fields;
    }

    /**
     * @return list<DataCategory>|null
     */
    private function categories(InputInterface $input): ?array
    {
        $values = $input->getOption('category');

        if (!\is_array($values) || [] === $values) {
            return null;
        }

        $categories = [];

        foreach ($values as $value) {
            $categories[] = DataCategory::fromString(\is_string($value) ? $value : '');
        }

        return $categories;
    }

    private function affected(InputInterface $input): ?int
    {
        $value = $this->text($input, 'affected');

        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^\d{1,9}$/D', $value)) {
            throw InvalidPrivacyValue::because('--affected is a number of people.');
        }

        return (int) $value;
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
