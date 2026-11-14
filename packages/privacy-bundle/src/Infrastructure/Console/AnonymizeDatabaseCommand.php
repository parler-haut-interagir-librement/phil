<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun;
use Phil\PrivacyBundle\Application\Anonymization\ProductionGuard;
use Phil\PrivacyBundle\Application\Contract\AnonymizationTarget;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationPlan;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use Phil\PrivacyBundle\Infrastructure\Doctrine\AnonymizationPlanReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:anonymize --dry-run
 *     bin/console phil:privacy:anonymize --confirm [--seed=7].
 *
 * Rewrites the personal data of the database with the anonymizers of its plan, in one transaction. `--dry-run`
 * prints the plan and the personal fields it leaves in clear; `--strict` fails while any is left. The database is
 * typically a copy restored from a production backup; backing it up and restoring it is another tool's work.
 *
 * The production guard runs before anything else, the dry run excepted, and cannot be bypassed.
 */
#[AsCommand(name: 'phil:privacy:anonymize', description: 'Anonymizes the personal data of the database')]
final class AnonymizeDatabaseCommand extends Command
{
    public function __construct(
        private readonly AnonymizationPlanReader $reader,
        private readonly AnonymizationRun $run,
        private readonly AnonymizationTarget $target,
        private readonly DatabaseIdentity $database,
        private readonly ProductionGuard $guard,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan and change nothing')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail while a personal field is left in clear')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'An integer that makes the random draws repeatable')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Anonymize: without it, nothing is done');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $option = $input->getOption('seed');
        $seed = \is_string($option) && 1 === preg_match('/^-?[0-9]{1,18}$/D', $option) ? (int) $option : null;

        if (null !== $option && null === $seed) {
            $output->writeln('The seed is an integer.');

            return Command::INVALID;
        }

        try {
            return $this->anonymize($input, $output, $seed);
        } catch (PrivacyError $privacyError) {
            $output->writeln($privacyError->getMessage());

            return Command::FAILURE;
        }
    }

    private function anonymize(InputInterface $input, OutputInterface $output, ?int $seed): int
    {
        $plan = $this->reader->plan();
        $this->describe($plan, $output);

        if (true === $input->getOption('strict') && [] !== $plan->uncovered) {
            $output->writeln('Nothing was anonymized: personal fields are left in clear.');

            return Command::FAILURE;
        }

        if (true === $input->getOption('dry-run')) {
            return Command::SUCCESS;
        }

        $this->guard->check($this->database);

        if (true !== $input->getOption('confirm')) {
            $output->writeln(\sprintf('Nothing was anonymized: add --confirm to rewrite the personal data of the %s for good.', $this->database->describe()));

            return Command::INVALID;
        }

        $report = $this->run->run($plan, $this->target, $seed);

        foreach ($report->rows as $table => $rows) {
            $output->writeln(\sprintf('%s: %d row(s) anonymized.', $table, $rows));
        }

        return Command::SUCCESS;
    }

    private function describe(AnonymizationPlan $plan, OutputInterface $output): void
    {
        if ($plan->isEmpty()) {
            $output->writeln('No column is anonymized: declare #[Anonymize] on the personal fields, or configure phil_privacy.anonymization.');
        }

        foreach ($plan->tables() as $table => $rules) {
            foreach ($rules as $rule) {
                $output->writeln(\sprintf('%s.%s: %s (%s)', $table, $rule->column, $rule->type, $rule->origin));
            }
        }

        foreach ($plan->uncovered as $field) {
            $output->writeln(\sprintf('Left in clear: %s::$%s (%s).', $field->class, $field->property, $field->category->value));
        }
    }
}
