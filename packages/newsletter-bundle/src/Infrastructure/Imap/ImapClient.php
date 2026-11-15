<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Imap;

use Closure;
use Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed;
use SensitiveParameter;

/**
 * The few IMAP4rev1 commands a bounce mailbox needs, over a stream the caller opens: log in, select a mailbox, find
 * the unread messages, read one without marking it, mark it read or deleted, expunge, log out.
 *
 * Every read is bounded: a line at the size given, 8 KiB by default, a literal at 10 MiB by default, a response at
 * 10 000 lines.
 * A tagged `NO` or `BAD` throws `ImapFailed`, and so does anything outside the protocol.
 */
final class ImapClient
{
    public const int MAX_LINE = 8192;

    public const int MAX_LINES = 10_000;

    /** @var resource|null */
    private $stream;

    private int $tag = 0;

    /**
     * @param Closure(): resource $connect opens the stream to the server
     */
    public function __construct(
        private readonly Closure $connect,
        private readonly int $maxLiteral = 10_485_760,
        private readonly int $maxLine = self::MAX_LINE,
    ) {
    }

    public function open(): void
    {
        $this->close();
        $this->stream = ($this->connect)();
        $this->tag = 0;
        $greeting = $this->line();

        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw ImapFailed::because('The server did not greet with OK.');
        }
    }

    public function login(string $user, #[SensitiveParameter] string $password): void
    {
        $this->command('LOGIN '.$this->quote($user).' '.$this->quote($password));
    }

    public function select(string $mailbox): void
    {
        $this->command('SELECT '.$this->quote($mailbox));
    }

    /**
     * The UIDs of the unread messages, in order.
     *
     * @return list<int>
     */
    public function unseen(): array
    {
        $uids = [];

        foreach ($this->command('UID SEARCH UNSEEN') as [$line]) {
            if (str_starts_with($line, '* SEARCH')) {
                foreach (explode(' ', trim(substr($line, 8))) as $uid) {
                    if (1 === preg_match('/^[1-9][0-9]{0,9}$/D', $uid)) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        sort($uids);

        return $uids;
    }

    /**
     * The raw message of a UID, read without marking it.
     */
    public function fetch(int $uid): string
    {
        foreach ($this->command(\sprintf('UID FETCH %d BODY.PEEK[]', $uid)) as [$line, $literals]) {
            if (1 === preg_match('/^\* [0-9]+ FETCH /', $line) && [] !== $literals) {
                return $literals[0];
            }
        }

        throw ImapFailed::because(\sprintf('The server sent no message for the UID %d.', $uid));
    }

    /**
     * Adds flags to a message: `\Seen`, `\Deleted`.
     */
    public function flag(int $uid, string ...$flags): void
    {
        foreach ($flags as $flag) {
            if (1 !== preg_match('/^\\\\[A-Za-z]+$/D', $flag)) {
                throw ImapFailed::because(\sprintf('"%s" is not a system flag.', $flag));
            }
        }

        $this->command(\sprintf('UID STORE %d +FLAGS.SILENT (%s)', $uid, implode(' ', $flags)));
    }

    public function expunge(): void
    {
        $this->command('EXPUNGE');
    }

    public function close(): void
    {
        if (!\is_resource($this->stream)) {
            $this->stream = null;

            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (ImapFailed) {
            // The connection is being closed: a server that answers badly changes nothing.
        }

        fclose($this->stream);
        $this->stream = null;
    }

    public function isOpen(): bool
    {
        return \is_resource($this->stream);
    }

    /**
     * Sends a command and reads its untagged responses up to its tagged one.
     *
     * @return list<array{0: string, 1: list<string>}> each untagged response, its literals apart
     */
    private function command(string $command): array
    {
        if (!\is_resource($this->stream)) {
            throw ImapFailed::because('The connection to the IMAP server is not open.');
        }

        $tag = 'A'.++$this->tag;
        fwrite($this->stream, $tag.' '.$command."\r\n");
        $responses = [];

        for ($count = 0; $count < self::MAX_LINES; ++$count) {
            [$line, $literals] = $this->response();

            if (str_starts_with($line, $tag.' ')) {
                if (!str_starts_with($line, $tag.' OK')) {
                    throw ImapFailed::because(\sprintf('The server refused %s.', explode(' ', $command, 2)[0]));
                }

                return $responses;
            }

            $responses[] = [$line, $literals];
        }

        throw ImapFailed::because('The server sent too many lines.');
    }

    /**
     * A response: a line, and the literals it announces with `{size}`, read in full.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function response(): array
    {
        $line = $this->line();
        $literals = [];

        while (1 === preg_match('/\{([0-9]{1,10})\}$/', $line, $size)) {
            $length = (int) $size[1];

            if ($length > $this->maxLiteral) {
                throw ImapFailed::because(\sprintf('The server sent a literal of %d bytes; at most %d are read.', $length, $this->maxLiteral));
            }

            $literals[] = $this->bytes($length);
            $line .= ' '.$this->line();
        }

        return [$line, $literals];
    }

    private function line(): string
    {
        \assert(\is_resource($this->stream));
        $line = fgets($this->stream, max(1, $this->maxLine + 2));

        if (false === $line) {
            throw ImapFailed::because('The IMAP server closed the connection.');
        }

        if (!str_ends_with($line, "\n")) {
            throw ImapFailed::because(\sprintf('The server sent a line longer than %d bytes.', $this->maxLine));
        }

        return rtrim($line, "\r\n");
    }

    private function bytes(int $length): string
    {
        \assert(\is_resource($this->stream));
        $bytes = '';

        while (\strlen($bytes) < $length) {
            $chunk = fread($this->stream, max(1, min(65_536, $length - \strlen($bytes))));

            if (false === $chunk || '' === $chunk) {
                throw ImapFailed::because('The IMAP server closed the connection inside a literal.');
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }

    private function quote(string $value): string
    {
        if (1 === preg_match('/[\r\n\x00]/', $value)) {
            throw ImapFailed::because('An IMAP string holds no line break nor NUL.');
        }

        return '"'.addcslashes($value, '"\\').'"';
    }
}
