<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Console;

use Override;
use Phil\ApiContract\Exception\UnreadableDocument;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use Phil\ApiContractBundle\Infrastructure\OpenApi\DocumentReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-contract:verify [document] [--level=recommended] [--fail-on=error] [--format=text].
 *
 * Verifies an OpenAPI document against the rules of a level, prints what it found, and fails when a finding reaches
 * the threshold: run in CI, it keeps the contract as the documentation describes it. Exits 0 when the document
 * passes, 1 when it fails, 2 when it cannot be read or an option is wrong.
 */
#[AsCommand(name: 'phil:api-contract:verify', description: 'Verifies an OpenAPI document against a level of rules')]
final class VerifyCommand extends Command
{
    /**
     * @param ?string $document the document verified when none is named
     * @param string  $level    the level when none is given: minimal, recommended or strict
     * @param string  $failOn   the threshold when none is given: error or warning
     */
    public function __construct(
        private readonly ContractVerifier $verifier,
        private readonly ?string $document = null,
        private readonly string $level = 'recommended',
        private readonly string $failOn = 'error',
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('document', InputArgument::OPTIONAL, 'The OpenAPI document, JSON or YAML; verification.document by default')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'The rules to run: minimal, recommended or strict', $this->level)
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'The severity that fails the command: error or warning', $this->failOn)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text or json', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('document') ?? $this->document;
        $level = $input->getOption('level');
        $level = \is_string($level) ? RuleLevel::tryFrom($level) : null;

        $failOn = $input->getOption('fail-on');
        $failOn = \is_string($failOn) ? Severity::tryFrom($failOn) : null;

        $format = $input->getOption('format');

        if (!\is_string($file)) {
            return $this->invalid($output, 'Name the document to verify, or set verification.document.');
        }

        if (!$level instanceof RuleLevel) {
            return $this->invalid($output, 'The level is minimal, recommended or strict.');
        }

        if (!$failOn instanceof Severity) {
            return $this->invalid($output, 'The threshold is error or warning.');
        }

        if ('text' !== $format && 'json' !== $format) {
            return $this->invalid($output, 'The format is text or json.');
        }

        try {
            $report = $this->verifier->verify(DocumentReader::read($file), $level);
        } catch (UnreadableDocument $unreadableDocument) {
            return $this->invalid($output, $unreadableDocument->getMessage());
        }

        if ('json' === $format) {
            $output->writeln(json_encode($report->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        } else {
            foreach ($report->findings as $finding) {
                $output->writeln(\sprintf('%s %s at %s: %s', Severity::Error === $finding->severity ? '<error>error</error>  ' : '<comment>warning</comment>', $finding->ruleId, OutputFormatter::escape($finding->location), OutputFormatter::escape($finding->message)));
            }

            $errors = \count($report->reaching(Severity::Error));
            $output->writeln(\sprintf('%d error(s), %d warning(s) at the %s level.', $errors, \count($report->findings) - $errors, $level->value));
        }

        return $report->fails($failOn) ? Command::FAILURE : Command::SUCCESS;
    }

    private function invalid(OutputInterface $output, string $message): int
    {
        $output->writeln(\sprintf('<error>%s</error>', OutputFormatter::escape($message)));

        return Command::INVALID;
    }
}
