<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentHistory;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Exception\NoticeRefused;
use Phil\PrivacyBundle\Application\Incident\IncidentNoticeDraft;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 *     bin/console phil:privacy:incident-notice 4f3c… --to=commission --locale=fr --output=avis-commission.md
 *     bin/console phil:privacy:incident-notice 4f3c… --to=commission --follow-up.
 *
 * Drafts a notice of a confidentiality incident from the register, in Markdown: to the Commission (section 3 of the
 * Québec *Regulation respecting confidentiality incidents*), its follow-up (section 4), to each person concerned
 * (section 5) or to the public (section 6). The draft goes to the standard output, or to a new file; what is left to
 * complete and what to do next go to the error output, or after a draft written to a file. Nothing is sent, nothing is
 * recorded, and the draft is never logged: the person in charge reviews it, sends it, then records the date with
 * `phil:privacy:incident`.
 */
#[AsCommand(name: 'phil:privacy:incident-notice', description: 'Drafts a notice of a confidentiality incident from the register; sends nothing')]
final class IncidentNoticeCommand extends Command
{
    public function __construct(
        private readonly IncidentRegister $register,
        private readonly IncidentNotices $notices,
        private readonly TwigIncidentNotice $renderer,
        private readonly ClockInterface $clock,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('incident', InputArgument::REQUIRED, 'The identifier of the incident in the register')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Who the notice is for: '.implode(', ', array_map(static fn (NoticeRecipient $recipient): string => $recipient->value, NoticeRecipient::cases())))
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'The language of the draft: '.implode(', ', TwigIncidentNotice::LOCALES), 'fr')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'A new file to write the draft to, instead of the standard output')
            ->addOption('follow-up', null, InputOption::VALUE_NONE, 'With --to=commission, the follow-up of section 4: the rubrics changed since the last notice')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'With --follow-up, the date of the last notice or follow-up; the date of the notice to the Commission by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $messages = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $recipient = NoticeRecipient::tryFrom($this->text($input, 'to') ?? '');
        $locale = $this->text($input, 'locale') ?? '';
        $followUp = true === $input->getOption('follow-up');
        $since = $this->text($input, 'since');

        if (!$recipient instanceof NoticeRecipient) {
            $messages->writeln(\sprintf('--to is one of %s.', implode(', ', array_map(static fn (NoticeRecipient $case): string => $case->value, NoticeRecipient::cases()))));

            return Command::INVALID;
        }

        $problem = match (true) {
            !\in_array($locale, TwigIncidentNotice::LOCALES, true) => \sprintf('--locale is one of %s.', implode(', ', TwigIncidentNotice::LOCALES)),
            $followUp && NoticeRecipient::Commission !== $recipient => 'A follow-up (section 4) is sent to the Commission only: --follow-up goes with --to=commission.',
            null !== $since && !$followUp => '--since goes with --follow-up.',
            default => null,
        };

        if (null !== $problem) {
            $messages->writeln($problem);

            return Command::INVALID;
        }

        $id = $input->getArgument('incident');
        $incident = \is_string($id) ? $this->register->find($id) : null;

        if (!$incident instanceof Incident) {
            $messages->writeln('No incident has that identifier.');

            return Command::FAILURE;
        }

        try {
            $draft = $followUp ? $this->followUp($incident, $since) : $this->notices->draft($incident, $recipient);
        } catch (NoticeRefused $noticeRefused) {
            $messages->writeln($noticeRefused->getMessage());

            return Command::FAILURE;
        }

        if ($draft->isFollowUp() && [] === $draft->changes()) {
            $messages->writeln(\sprintf('No rubric of section 3 changed since %s: there is no follow-up to draft.', $draft->since?->format('Y-m-d H:i') ?? ''));

            return Command::SUCCESS;
        }

        $markdown = $this->renderer->render($draft, $locale, $this->clock->now()->getTimezone());
        $file = $this->text($input, 'output');

        if (null === $file) {
            $output->write($markdown, false, OutputInterface::OUTPUT_RAW);
        } elseif (!$this->write($file, $markdown, $messages)) {
            return Command::FAILURE;
        }

        $this->remind($draft, $messages);

        return Command::SUCCESS;
    }

    private function followUp(Incident $incident, ?string $since): IncidentNoticeDraft
    {
        if (!$this->register instanceof IncidentHistory) {
            throw NoticeRefused::followUpWithoutHistory();
        }

        $moment = null;

        if (null !== $since) {
            try {
                $moment = new DateTimeImmutable($since, $this->clock->now()->getTimezone());
            } catch (Exception) {
                throw NoticeRefused::invalidSince();
            }
        }

        return $this->notices->followUp($incident, $this->register->revisions($incident->id), $moment);
    }

    /**
     * Writes the draft to a file that does not exist yet, readable by its owner only: a draft names the people and the
     * information an incident concerns.
     */
    private function write(string $file, string $markdown, OutputInterface $messages): bool
    {
        if ($this->filesystem->exists($file)) {
            $messages->writeln(\sprintf('%s already exists: give a new file to --output.', $file));

            return false;
        }

        try {
            $this->filesystem->dumpFile($file, $markdown);
            $this->filesystem->chmod($file, 0o600);
        } catch (IOException) {
            $messages->writeln(\sprintf('The draft could not be written to %s.', $file));

            return false;
        }

        $messages->writeln(\sprintf('Draft written to %s.', $file));

        return true;
    }

    private function remind(IncidentNoticeDraft $draft, OutputInterface $messages): void
    {
        $incident = $draft->incident;

        if ([] !== $draft->missing) {
            $messages->writeln(\sprintf('To complete in the draft: %s.', implode('; ', array_map(static fn (NoticeRubric $rubric): string => \sprintf('s. %d, para. %d (%s)', $draft->recipient->section(), $rubric->paragraph($draft->recipient) ?? 0, str_replace('_', ' ', $rubric->value)), $draft->missing))));
        }

        if (!$incident->seriousRisk) {
            $messages->writeln('The register does not record a risk of serious injury: the notices are owed for one only (section 3.5 of the Act respecting the protection of personal information in the private sector).');
        }

        $messages->writeln(match ($draft->recipient) {
            NoticeRecipient::Commission => $draft->isFollowUp()
                ? 'Nothing was sent. Review the follow-up, send it to the Commission promptly (section 4), and keep its date: give it to --since for the next follow-up.'
                : \sprintf('Nothing was sent. Review the draft, send it to the Commission in writing, then record the date: phil:privacy:incident --id=%s --authority-notified=<date>.', $incident->id),
            NoticeRecipient::Subject => \sprintf('Nothing was sent. Review the draft, send it to each person concerned, then record the date: phil:privacy:incident --id=%s --subjects-notified=<date>.', $incident->id),
            NoticeRecipient::Public => \sprintf('Nothing was sent. Review the draft, publish it by a means that can reasonably be expected to reach the people concerned, then record it: phil:privacy:incident --id=%s --public-notice=<date> --public-notice-reason=%s.', $incident->id, $incident->publicNoticeReason?->value),
        });

        if (NoticeRecipient::Commission === $draft->recipient) {
            return;
        }

        $reason = $incident->publicNoticeReason;

        $messages->writeln(match (true) {
            !$reason instanceof PublicNoticeReason => 'The notice goes to each person; a public notice replaces it only when sending it would cause greater injury, would be excessively difficult, or the organisation has no contact details for the people concerned (section 6).',
            $reason->replacesIndividualNotice() => \sprintf('The register records a public notice reason (%s) that replaces the notice to each person (section 6).', $reason->value),
            default => 'A public notice given to act quickly does not replace the notice to each person, which is still owed promptly (section 6).',
        });
    }

    private function text(InputInterface $input, string $option): ?string
    {
        $value = $input->getOption($option);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
