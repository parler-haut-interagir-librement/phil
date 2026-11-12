<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\Console;

use FilesystemIterator;
use Override;
use Phil\UxKit\Application\ImageMigration;
use Phil\UxKit\Application\ImageMigrationFinding;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:ux-kit:migrate-images [templates/] [--write].
 *
 * Lists, in the Twig templates of the paths given, the `img` and `picture` elements written by hand, with the `Image`
 * or `Picture` recipe proposed in place of each, or what stands in its way. Nothing changes without `--write`, which
 * rewrites only the elements proposed and leaves every other as it is, for a person to finish.
 */
#[AsCommand(name: 'phil:ux-kit:migrate-images', description: 'Proposes the Image and Picture recipes in place of the img and picture elements of Twig templates')]
final class MigrateImagesCommand extends Command
{
    public function __construct(
        private readonly ImageMigration $migration,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'The templates or directories of templates to read, templates/ of the project by default')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Rewrite the elements proposed, and only them');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = [];

        foreach ((array) $input->getArgument('paths') as $path) {
            if (\is_string($path)) {
                $paths[] = $path;
            }
        }

        $write = true === $input->getOption('write');
        $proposed = 0;
        $left = 0;

        $paths = [] === $paths ? [$this->projectDir.'/templates'] : $paths;

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $output->writeln($path.': no template or directory there.');
            }
        }

        foreach ($this->templates($paths) as $file) {
            $template = (string) file_get_contents($file);
            $findings = $this->migration->find($template);

            foreach ($findings as $finding) {
                $this->report($output, $file, $finding, $write);
                null === $finding->proposal ? ++$left : ++$proposed;
            }

            $migrated = $this->migration->apply($template, $findings);

            if ($write && $migrated !== $template) {
                file_put_contents($file, $migrated);
            }
        }

        $output->writeln(\sprintf('%d element(s) %s, %d left as they are.', $proposed, $write ? 'rewritten' : 'proposed', $left));

        if (!$write && $proposed > 0) {
            $output->writeln('Read the proposals, then run again with --write to rewrite them.');
        }

        return Command::SUCCESS;
    }

    private function report(OutputInterface $output, string $file, ImageMigrationFinding $finding, bool $write): void
    {
        $output->writeln(\sprintf('%s:%d %s', $file, $finding->line, null === $finding->proposal ? 'left as it is' : ($write ? 'rewritten' : 'proposed')));
        $output->writeln('  - '.preg_replace('/\s+/', ' ', $finding->original), OutputInterface::OUTPUT_RAW);

        if (null !== $finding->proposal) {
            $output->writeln('  + '.$finding->proposal, OutputInterface::OUTPUT_RAW);
        }

        foreach ($finding->notes as $note) {
            $output->writeln('    '.$note, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * The Twig templates of the paths, each file given and those under each directory, in the order of their names.
     *
     * @param list<string> $paths
     *
     * @return array<int, string>
     */
    private function templates(array $paths): array
    {
        $templates = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $templates[] = $path;
            } elseif (is_dir($path)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.twig')) {
                        $templates[] = $file->getPathname();
                    }
                }
            }
        }

        sort($templates);

        return array_unique($templates);
    }
}
