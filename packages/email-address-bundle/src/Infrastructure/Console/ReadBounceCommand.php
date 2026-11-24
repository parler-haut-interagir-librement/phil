<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Console;

use Phil\BounceParser\Limits;
use Phil\EmailAddressBundle\Application\Bounces\BounceIngestor;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads one raw message of the bounce mailbox — from a file, or from the standard input as a mail server's pipe gives
 * it — and has the bounce rules decide each notice it gives. It prints one decision per line, never an address.
 *
 *     postfix: bounces  unix  -  n  n  -  -  pipe  user=app argv=/srv/app/bin/console phil:email-address:read-bounce
 */
#[AsCommand(name: 'phil:email-address:read-bounce', description: 'Reads a raw bounce or abuse report and applies the bounce rules.')]
final readonly class ReadBounceCommand
{
    /**
     * @param resource|null $stdin the standard input, or another stream in tests
     */
    public function __construct(
        private BounceIngestor $ingestor,
        private Limits $limits = new Limits(),
        private mixed $stdin = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The file of the message; the standard input when left out or "-".')]
        string $file = '-',
    ): int {
        $raw = $this->read($file);

        if (null === $raw) {
            $io->error(\sprintf('The message "%s" cannot be read.', $file));

            return Command::FAILURE;
        }

        $decisions = $this->ingestor->ingest($raw);

        foreach ($decisions as $decision) {
            $io->writeln($decision->value);
        }

        if ([] === $decisions) {
            $io->writeln('ignored', SymfonyStyle::VERBOSITY_VERBOSE);
        }

        return Command::SUCCESS;
    }

    /**
     * The message, cut one byte past the parser's limit, which then reads what it can of it.
     */
    private function read(string $file): ?string
    {
        $stream = '-' === $file ? ($this->stdin ?? \STDIN) : (is_file($file) ? @fopen($file, 'r') : false);

        if (!\is_resource($stream)) {
            return null;
        }

        $raw = stream_get_contents($stream, $this->limits->maxBytes + 1);

        if ('-' !== $file) {
            fclose($stream);
        }

        return false === $raw ? null : $raw;
    }
}
