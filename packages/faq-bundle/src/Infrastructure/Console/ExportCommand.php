<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Console;

use Phil\FaqBundle\Application\FaqTransfer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes every section and question as YAML, on the standard output or in a file.
 */
#[AsCommand(name: 'phil:faq:export', description: 'Writes the sections and questions as YAML')]
final readonly class ExportCommand
{
    public function __construct(
        private FaqTransfer $transfer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The file to write; the standard output when left out')]
        ?string $file = null,
    ): int {
        $yaml = Yaml::dump($this->transfer->export(), 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        if (null === $file) {
            $io->write($yaml);

            return Command::SUCCESS;
        }

        if (false === @file_put_contents($file, $yaml)) {
            $io->error(\sprintf('The file "%s" cannot be written.', $file));

            return Command::FAILURE;
        }

        $io->success(\sprintf('The questions are written to "%s".', $file));

        return Command::SUCCESS;
    }
}
