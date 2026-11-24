<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Console;

use Phil\FaqBundle\Application\FaqTransfer;
use Phil\FaqBundle\Domain\Exception\FaqException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads sections and questions from a YAML file written by `phil:faq:export`: what exists is changed, what does not is
 * added, the order becomes the file's, and nothing is removed.
 */
#[AsCommand(name: 'phil:faq:import', description: 'Reads sections and questions from a YAML file')]
final readonly class ImportCommand
{
    /** The largest file read, in bytes. */
    public const int MAX_BYTES = 5_242_880;

    public function __construct(
        private FaqTransfer $transfer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The YAML file to read')]
        string $file,
    ): int {
        $size = is_file($file) ? filesize($file) : false;

        if (false === $size || $size > self::MAX_BYTES) {
            $io->error(\sprintf('The file "%s" is not a readable file of %d bytes at most.', $file, self::MAX_BYTES));

            return Command::FAILURE;
        }

        try {
            $counts = $this->transfer->import(Yaml::parse((string) file_get_contents($file)));
        } catch (ParseException $exception) {
            $io->error('The file is not valid YAML: '.$exception->getMessage());

            return Command::FAILURE;
        } catch (FaqException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d sections and %d questions are read.', $counts['sections'], $counts['questions']));

        return Command::SUCCESS;
    }
}
