<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\Console;

use Override;
use Phil\FontsBundle\Application\FontFaces;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 *     bin/console phil:fonts:css [--output=assets/styles/fonts.css].
 *
 * Writes the `@font-face` rules of the directory, to the output or to a file.
 */
#[AsCommand(name: 'phil:fonts:css', description: 'Writes the @font-face rules of the font directory')]
final class FontFacesCommand extends Command
{
    public function __construct(
        private readonly FontFaces $faces,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The file to write the rules to, in place of the output');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $css = $this->faces->css();
        $file = $input->getOption('output');

        if (!\is_string($file)) {
            $output->write($css);

            return Command::SUCCESS;
        }

        $this->filesystem->dumpFile($file, $css);
        $output->writeln(\sprintf('The @font-face rules are written to "%s".', $file));

        return Command::SUCCESS;
    }
}
