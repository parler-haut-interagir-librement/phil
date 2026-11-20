<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:erase 0192f3c4-… --confirm.
 *
 * Erases a person from every module, and says what each store did — erased, anonymised, kept until it expires. A
 * module that fails is named and the others still run; running the command again retries, erasers being idempotent.
 *
 * A failure is shown by its class; its message, which can quote the person's data, only with `-v`.
 */
#[AsCommand(name: 'phil:privacy:erase', description: 'Erases a data subject from every module')]
final class EraseSubjectCommand extends Command
{
    public function __construct(
        private readonly SubjectErasure $erasure,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'The identifier of the data subject')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Erase: without it, nothing is done');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $subject = DataSubject::fromString($input->getArgument('subject'));
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            $output->writeln($invalidPrivacyValue->getMessage());

            return Command::INVALID;
        }

        if (true !== $input->getOption('confirm')) {
            $output->writeln(\sprintf('Nothing was erased: add --confirm to erase %s from every module.', $subject));

            return Command::INVALID;
        }

        try {
            $this->report($output, $this->erasure->erase($subject));
        } catch (ErasureIncomplete $erasureIncomplete) {
            $this->report($output, $erasureIncomplete->completed);

            foreach ($erasureIncomplete->failures as $module => $failure) {
                $output->writeln(\sprintf('%s: failed, %s', $module, $failure::class));

                if ($output->isVerbose()) {
                    $output->writeln(\sprintf('%s: %s', $module, $failure->getMessage()));
                }
            }

            $output->writeln($erasureIncomplete->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<ErasureReport> $reports
     */
    private function report(OutputInterface $output, array $reports): void
    {
        foreach ($reports as $report) {
            foreach ($report->effects as $store => $effect) {
                $output->writeln(\sprintf('%s: %s, %s%s', $report->module->value, $store, $effect->value, $effect->leavesPersonalData() ? ' (personal data left)' : ''));
            }
        }
    }
}
