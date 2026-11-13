<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Console;

use Override;
use Phil\Epub\Exception\EpubException;
use Phil\EpubBundle\Application\Epubs;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 *     bin/console phil:epub:write story 42 [--output=var/story-42.epub].
 *
 * Writes the book a source builds from an identifier: to the file given, or to the storage under `<source>/<id>.epub`.
 */
#[AsCommand(name: 'phil:epub:write', description: 'Writes the EPUB of a book source and an identifier')]
final class WriteEpubCommand extends Command
{
    public function __construct(
        private readonly Epubs $epubs,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'The name of the book source')
            ->addArgument('id', InputArgument::REQUIRED, 'The identifier the source builds the book from')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The file to write, in place of the storage');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $source */
        $source = $input->getArgument('source');
        /** @var string $id */
        $id = $input->getArgument('id');
        $file = $input->getOption('output');

        try {
            $book = $this->epubs->build($source, $id);

            if (\is_string($file)) {
                $this->filesystem->dumpFile($file, $this->epubs->write($book));
                $output->writeln(\sprintf('The book is written to "%s".', $file));

                return Command::SUCCESS;
            }

            $output->writeln(\sprintf('The book is kept at "%s".', $this->epubs->store($book, $source.'/'.$id.'.epub')));
        } catch (EpubException|IOException $exception) {
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
