<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use DateTimeImmutable;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\Incident;
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
 * Records a confidentiality incident, or, with `--id`, completes one already recorded: what was done, when the
 * authority and the people concerned were told, when it was closed. A date left out is now for `--discovered`, and is
 * not changed for the others. The command says which notifications a risk of serious injury still asks for.
 */
#[AsCommand(name: 'phil:privacy:incident', description: 'Records or completes a line of the register of confidentiality incidents')]
final class IncidentCommand extends Command
{
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
            ->addArgument('summary', InputArgument::OPTIONAL, 'What happened, for a new incident')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'The incident to complete')
            ->addOption('discovered', null, InputOption::VALUE_REQUIRED, 'When it was discovered; now by default')
            ->addOption('occurred', null, InputOption::VALUE_REQUIRED, 'When it happened, when known')
            ->addOption('category', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A kind of personal information concerned')
            ->addOption('affected', null, InputOption::VALUE_REQUIRED, 'How many people are concerned')
            ->addOption('serious-risk', null, InputOption::VALUE_NEGATABLE, 'Whether it presents a risk of serious injury')
            ->addOption('measures', null, InputOption::VALUE_REQUIRED, 'What was done to reduce the risk and prevent another')
            ->addOption('authority-notified', null, InputOption::VALUE_REQUIRED, 'When the authority was notified')
            ->addOption('subjects-notified', null, InputOption::VALUE_REQUIRED, 'When the people concerned were notified')
            ->addOption('closed', null, InputOption::VALUE_REQUIRED, 'When it was closed');
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

        return Command::SUCCESS;
    }

    private function incident(InputInterface $input): ?Incident
    {
        $categories = $this->categories($input);
        $affected = $this->affected($input);
        $risk = $input->getOption('serious-risk');
        $measures = $this->text($input, 'measures');
        $id = $this->text($input, 'id');

        if (null === $id) {
            $summary = $input->getArgument('summary');

            if (!\is_string($summary)) {
                throw InvalidPrivacyValue::because('A new incident needs its summary; to complete one, give its --id.');
            }

            // What the options give beside the dates of the discovery is applied below, as for a line being completed.
            $incident = Incident::record($summary, $this->moment($input, 'discovered') ?? $this->clock->now(), $this->moment($input, 'occurred'), [], null, false);
        } else {
            $incident = $this->register->find($id);

            if (!$incident instanceof Incident) {
                return null;
            }
        }

        return $incident->update(
            $measures,
            \is_bool($risk) ? $risk : null,
            $affected,
            $categories,
            $this->moment($input, 'authority-notified'),
            $this->moment($input, 'subjects-notified'),
            $this->moment($input, 'closed'),
        );
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
