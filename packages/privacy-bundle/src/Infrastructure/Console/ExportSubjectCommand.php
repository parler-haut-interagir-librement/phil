<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:export 0192f3c4-… --output=answer.json [--force].
 *
 * The answer to an access request: what every module holds about a person, one section per module, as JSON. Who
 * asked and whether they are who they claim stays the application's to check.
 *
 * The file holds every personal record of one person, so it is created readable and writable by its owner only
 * (`0600`), whatever the umask; an existing file is replaced only with `--force`, and made `0600` too. A write that
 * fails or falls short is a failure, never a "the answer is in" over an empty or missing file; a file this command
 * created and could not fill is removed.
 */
#[AsCommand(name: 'phil:privacy:export', description: 'Exports what every module holds about a data subject, as JSON')]
final class ExportSubjectCommand extends Command
{
    public function __construct(
        private readonly SubjectExport $export,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'The identifier of the data subject')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'A file to write the answer to, instead of the output; created readable by its owner only')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace the output file if it exists');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $subject = DataSubject::fromString($this->string($input->getArgument('subject')));
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            $output->writeln($invalidPrivacyValue->getMessage());

            return Command::INVALID;
        }

        $json = json_encode($this->export->export($subject), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
        $file = $input->getOption('output');

        if (!\is_string($file)) {
            $output->write($json);

            return Command::SUCCESS;
        }

        $failure = $this->write($file, $json, true === $input->getOption('force'));

        if (null !== $failure) {
            $output->writeln(\sprintf('The answer for %s was not written: %s', $subject, $failure));

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('The answer for %s is in %s.', $subject, $file));

        return Command::SUCCESS;
    }

    /**
     * @return string|null why the file was not written, `null` once it holds the whole answer
     */
    private function write(string $file, string $json, bool $force): ?string
    {
        // "x" refuses an existing file in the same call that creates it; the umask keeps a new one to its owner.
        $umask = umask(0o077);

        try {
            $handle = @fopen($file, $force ? 'c' : 'x');
        } finally {
            umask($umask);
        }

        if (false === $handle) {
            return file_exists($file) && !$force ? \sprintf('%s exists; pass --force to replace it.', $file) : \sprintf('%s cannot be opened for writing.', $file);
        }

        // A replaced file is cut to the answer once it is written over: nothing of what it held is left after it.
        $written = chmod($file, 0o600) && \strlen($json) === fwrite($handle, $json) && ftruncate($handle, \strlen($json)) && fflush($handle);

        if (fclose($handle) && $written) {
            return null;
        }

        if (!$force) {
            // Created by this call: nothing but a truncated answer would be left.
            @unlink($file);
        }

        return \sprintf('%s could not be written in full.', $file);
    }

    private function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
