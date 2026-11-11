<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Infrastructure\Console;

use Override;
use Phil\RouteAccessCheck\Baseline;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Exception\InvalidBaseline;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

/**
 *     bin/console phil:route-access:check [--format=text|json|github|junit] [--baseline=FILE] [--generate-baseline[=FILE]] [--only-problems] [--strict].
 *
 * Checks every route of the router against the access rules, writes the report in the format asked for, and exits
 * with 1 when a route is uncovered or conflicts and the baseline does not tolerate it — or, with `--strict`, when a
 * warning remains. `--generate-baseline` writes the current failures to the baseline file instead, and exits with 0.
 */
#[AsCommand(name: 'phil:route-access:check', description: 'Checks that every route has an access rule, or is public on purpose')]
final class CheckRouteAccessCommand extends Command
{
    public const string DEFAULT_BASELINE = 'route-access-baseline.json';

    /** @var array<string, ReportFormatter> */
    private readonly array $formatters;

    /**
     * @param iterable<ReportFormatter> $formatters
     * @param string|null               $baseline   the baseline file of the configuration
     */
    public function __construct(
        private readonly RouteAccessChecker $checker,
        private readonly RouterInterface $router,
        iterable $formatters,
        private readonly ?string $baseline = null,
    ) {
        $byName = [];

        foreach ($formatters as $formatter) {
            $byName[$formatter->name()] = $formatter;
        }

        $this->formatters = $byName;

        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'The format of the report: '.implode(', ', array_keys($this->formatters)), 'text')
            ->addOption('baseline', 'b', InputOption::VALUE_REQUIRED, 'The baseline file whose failures are tolerated')
            ->addOption('generate-baseline', null, InputOption::VALUE_OPTIONAL, 'Writes the current failures to this baseline file, or to the configured one', false)
            ->addOption('only-problems', null, InputOption::VALUE_NONE, 'Leaves out the routes that pass')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fails on warnings too: unused access_control entries, stale baseline entries, inexact sample paths');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $format = $input->getOption('format');
        $format = \is_string($format) ? $format : '';

        $formatter = $this->formatters[$format] ?? null;

        if (!$formatter instanceof ReportFormatter) {
            $errors->writeln(\sprintf('The format "%s" is unknown; the formats are %s.', $format, implode(', ', array_keys($this->formatters))));

            return Command::INVALID;
        }

        $routes = $this->router->getRouteCollection();
        $generate = $input->getOption('generate-baseline');

        if (false !== $generate) {
            $file = \is_string($generate) ? $generate : $this->baseline ?? self::DEFAULT_BASELINE;
            $baseline = Baseline::fromReport($this->checker->check($routes));
            new Filesystem()->dumpFile($file, $baseline->toJson());
            $errors->writeln(\sprintf('The baseline %s tolerates %d failing routes.', $file, \count($baseline->entries)));

            return Command::SUCCESS;
        }

        $option = $input->getOption('baseline');
        $file = \is_string($option) ? $option : $this->baseline;

        try {
            $baseline = null === $file ? new Baseline() : $this->readBaseline($file);
        } catch (InvalidBaseline $invalidBaseline) {
            $errors->writeln($file.': '.$invalidBaseline->getMessage());

            return Command::INVALID;
        }

        $report = $this->checker->check($routes, $baseline);
        $output->write($formatter->format($report, true === $input->getOption('only-problems')), false, OutputInterface::OUTPUT_RAW);

        $fails = $report->hasFailures() || (true === $input->getOption('strict') && $report->hasWarnings());

        return $fails ? Command::FAILURE : Command::SUCCESS;
    }

    private function readBaseline(string $file): Baseline
    {
        if (!is_file($file) || !is_readable($file)) {
            throw InvalidBaseline::because('the file cannot be read.');
        }

        // One byte past the limit, so that the library refuses a larger file without it being read whole.
        return Baseline::fromJson((string) file_get_contents($file, length: Baseline::MAX_BYTES + 1));
    }
}
