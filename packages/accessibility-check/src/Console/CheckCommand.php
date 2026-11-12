<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Console;

use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Exception\AccessibilityCheckException;
use Phil\AccessibilityCheck\Severity;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:accessibility:check article.html
 *     bin/console phil:accessibility:check article.json --format=json
 *     bin/console phil:accessibility:check '<img src="cat.jpg">' --min-severity=warning.
 *
 * Checks a file, or the HTML given as the argument itself: HTML, or a UJO document when the file ends with `.json` or
 * `--ujo` is given. Never an address: the command reads files, it does not fetch pages. One line per finding, or the
 * report in JSON; the command fails when a finding is the minimum severity or graver, and is invalid when the content
 * cannot be read or is refused.
 */
#[AsCommand(name: 'phil:accessibility:check', description: 'Checks the accessibility of an HTML file or a UJO document')]
final class CheckCommand extends Command
{
    public function __construct(
        private readonly AccessibilityChecker $checker = new AccessibilityChecker(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The file to check, or the HTML itself')
            ->addOption('ujo', null, InputOption::VALUE_NONE, 'Read the content as a UJO document, whatever the name of the file')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output: text or json', 'text')
            ->addOption('min-severity', null, InputOption::VALUE_REQUIRED, 'The severity from which a finding fails the check: error, warning or notice', Severity::Error->value)
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, "The locale of the messages; the policy's by default")
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'The language of a content that does not say its own');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $file = \is_string($file) ? $file : '';

        $format = $input->getOption('format');
        $minimum = $input->getOption('min-severity');
        $minimum = \is_string($minimum) ? Severity::tryFrom($minimum) : null;

        $language = $input->getOption('language');
        $locale = $input->getOption('locale');

        if (null === $minimum || !\in_array($format, ['text', 'json'], true)) {
            $io->error('The format is text or json, and the minimum severity error, warning or notice.');

            return Command::INVALID;
        }

        $content = $this->read($file);

        if (null === $content) {
            $io->error(\sprintf('"%s" is neither a readable file nor HTML; the command reads files, never addresses.', $file));

            return Command::INVALID;
        }

        try {
            $language = \is_string($language) ? $language : null;
            $report = true === $input->getOption('ujo') || str_ends_with(strtolower($file), '.json')
                ? $this->checker->checkUjo(new Ujo()->decode($content), $language)
                : $this->checker->checkHtml($content, $language);
        } catch (AccessibilityCheckException|UjoException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $report = \is_string($locale) ? $report->inLocale($locale) : $report;

        if ('json' === $format) {
            $output->writeln(json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        } else {
            foreach ($report->findings as $finding) {
                $io->writeln(\sprintf(
                    '[%s] %s (WCAG %s, %s) %s: %s',
                    $finding->severity->value,
                    $finding->rule,
                    $finding->criterion->value,
                    $finding->criterion->level()->value,
                    $finding->location->describe(),
                    $report->message($finding),
                ));
            }

            $io->writeln(\sprintf('%d finding(s): %d error(s), %d warning(s), %d notice(s).', \count($report), \count($report->withSeverity(Severity::Error)), \count($report->withSeverity(Severity::Warning)), \count($report->withSeverity(Severity::Notice))));
        }

        return $report->passes($minimum) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * The content of the file, at most one byte more than the policy checks, or the argument itself when it is HTML;
     * `null` for an address, or for a text that is neither.
     */
    private function read(string $file): ?string
    {
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $file)) {
            return null;
        }

        if (is_file($file) && is_readable($file)) {
            /** @var int<1, max> $length the policy refuses a maximum below 1 */
            $length = $this->checker->policy()->maxBytes + 1;
            $content = file_get_contents($file, length: $length);

            return false === $content ? '' : $content;
        }

        return str_contains($file, '<') || str_starts_with(ltrim($file), '{') ? $file : null;
    }
}
