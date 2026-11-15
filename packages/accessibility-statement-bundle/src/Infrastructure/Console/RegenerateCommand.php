<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Console;

use Override;
use Phil\AccessibilityStatement\Evaluation;
use Phil\AccessibilityStatement\Exception\AccessibilityStatementException;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatementBundle\Application\Exception\NothingToRegenerate;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:accessibility-statement:generate [--dry-run] [--format=json].
 *
 * Writes the next version of the statement from the latest audit and publishes it; with `--dry-run`, or when the
 * statement comes from the configuration, shows it without publishing — in JSON, the data to write in the
 * configuration.
 */
#[AsCommand(name: self::NAME, description: 'Regenerates the accessibility statement from the latest audit')]
final class RegenerateCommand extends Command
{
    public const string NAME = 'phil:accessibility-statement:generate';

    public function __construct(
        private readonly StatementRegenerator $regenerator,
        private readonly StatementRepository $statements,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Shows the next version without publishing it')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text, a summary, or json, the statement as data', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');

        if ('text' !== $format && 'json' !== $format) {
            $io->error('The format is text or json.');

            return Command::INVALID;
        }

        $dryRun = true === $input->getOption('dry-run');
        $publish = !$dryRun && $this->statements->writable();

        try {
            $statement = $publish ? $this->regenerator->publish() : $this->regenerator->preview();
        } catch (NothingToRegenerate $nothingToRegenerate) {
            $io->warning($nothingToRegenerate->getMessage());

            return Command::FAILURE;
        } catch (AccessibilityStatementException $accessibilityStatementException) {
            $io->error($accessibilityStatementException->getMessage());

            return Command::INVALID;
        }

        if ('json' === $format) {
            $output->writeln(json_encode($statement->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $this->summarize($io, $statement);

        if ($publish) {
            $io->success(\sprintf('Version %d of the accessibility statement is published.', $statement->version));
        } elseif (!$dryRun) {
            $io->note('The statement comes from the configuration: write this version there, as --format=json gives it.');
        }

        return Command::SUCCESS;
    }

    private function summarize(SymfonyStyle $io, Statement $statement): void
    {
        $evaluation = $statement->evaluation;
        $io->title(\sprintf('Accessibility statement, version %d', $statement->version));
        $io->definitionList(
            ['Target' => $statement->target->describe()],
            ['Status' => $statement->status->value],
            ['Evaluated' => $evaluation instanceof Evaluation ? $evaluation->evaluatedAt->format('Y-m-d H:i').', '.$evaluation->pages.' pages' : '-'],
        );

        if ([] === $statement->nonConformities) {
            $io->text('No success criterion of the target fails.');

            return;
        }

        $io->table(['Criterion', 'Reason', 'Occurrences', 'Source'], array_map(static fn (NonConformity $nonConformity): array => [
            $nonConformity->criterion->value.' '.$nonConformity->criterion->title(),
            $nonConformity->reason->value,
            0 === $nonConformity->occurrences ? '-' : number_format($nonConformity->occurrences),
            $nonConformity->fromAudit ? 'audit' : 'written',
        ], $statement->nonConformities));
    }
}
