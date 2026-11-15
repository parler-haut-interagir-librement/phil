<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed;
use Phil\NewsletterBundle\Infrastructure\Imap\ImapBounceMailbox;
use Phil\NewsletterBundle\Infrastructure\Imap\ImapClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapClient::class)]
#[CoversClass(ImapBounceMailbox::class)]
#[CoversClass(ImapFailed::class)]
final class ImapTest extends TestCase
{
    /** @var list<resource> */
    private array $streams = [];

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testTheMailboxReadsTheUnreadMessagesAndMarksThemRead(): void
    {
        [$client, $server] = $this->connection(implode("\r\n", [
            '* OK IMAP ready',
            'A1 OK LOGIN completed',
            '* 3 EXISTS',
            'A2 OK [READ-WRITE] SELECT completed',
            '* SEARCH 9 4 x 0',
            'A3 OK SEARCH completed',
            '* 1 FETCH (UID 4 BODY[] {10}',
            "First\r\nmsg)",
            'A4 OK FETCH completed',
            'A5 OK STORE completed',
            'A6 OK LOGOUT completed',
            '',
        ]));
        $mailbox = new ImapBounceMailbox($client, 'bounces@example.com', 'p"ss\\word', 'Bounces');

        $read = [];

        foreach ($mailbox->unread(1) as $id => $raw) {
            $read[$id] = $raw;
            $mailbox->acknowledge((string) $id);
        }

        self::assertSame(['4' => "First\r\nmsg"], $read);
        self::assertSame([
            'A1 LOGIN "bounces@example.com" "p\"ss\\\\word"',
            'A2 SELECT "Bounces"',
            'A3 UID SEARCH UNSEEN',
            'A4 UID FETCH 4 BODY.PEEK[]',
            'A5 UID STORE 4 +FLAGS.SILENT (\Seen)',
            'A6 LOGOUT',
        ], $this->sent($server));
        self::assertFalse($client->isOpen());
        $mailbox->acknowledge('4');
        $mailbox->reset();
    }

    public function testDeletingMarksDeletedAndExpunges(): void
    {
        [$client, $server] = $this->connection(implode("\r\n", ['* PREAUTH ready', 'A1 OK', 'A2 OK', '* SEARCH 5', 'A3 OK', '* 1 FETCH (BODY[] {1}', 'x)', 'A4 OK', 'A5 OK', 'A6 OK', 'A7 OK', '']));
        $mailbox = new ImapBounceMailbox($client, 'u', 'p', delete: true);

        foreach ($mailbox->unread(10) as $id => $raw) {
            $mailbox->acknowledge((string) $id);
            $mailbox->acknowledge('not-a-uid');
        }

        self::assertSame(['A1 LOGIN "u" "p"', 'A2 SELECT "INBOX"', 'A3 UID SEARCH UNSEEN', 'A4 UID FETCH 5 BODY.PEEK[]', 'A5 UID STORE 5 +FLAGS.SILENT (\Seen \Deleted)', 'A6 EXPUNGE', 'A7 LOGOUT'], $this->sent($server));
    }

    #[DataProvider('failures')]
    public function testTheClientRefusesWhatIsOutsideTheProtocol(string $script, string $message): void
    {
        [$client] = $this->connection($script, 16);

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains($message);

        $client->open();
        $client->login('u', 'p');
        $client->fetch(1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function failures(): iterable
    {
        yield 'no greeting' => ["* BYE go away\r\n", 'The server did not greet with OK.'];
        yield 'a refused login' => ["* OK\r\nA1 NO bad password\r\n", 'The server refused LOGIN.'];
        yield 'no message' => ["* OK\r\nA1 OK\r\nA2 OK\r\n", 'The server sent no message for the UID 1.'];
        yield 'a large literal' => ["* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {17}\r\n", 'The server sent a literal of 17 bytes; at most 16 are read.'];
        yield 'a literal cut short' => ["* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {10}\r\nabc", 'The IMAP server closed the connection inside a literal.'];
        yield 'a long line' => ["* OK\r\n".str_repeat('x', 100)."\r\n", 'The server sent a line longer than 64 bytes.'];
        yield 'closed' => ["* OK\r\n", 'The IMAP server closed the connection.'];
    }

    public function testFlagsAndStringsAreChecked(): void
    {
        [$client] = $this->connection("* OK\r\nA1 OK\r\n");
        $client->open();

        try {
            $client->flag(1, 'Seen');
            self::fail('Expected a refusal.');
        } catch (ImapFailed $imapFailed) {
            self::assertSame('"Seen" is not a system flag.', $imapFailed->getMessage());
        }

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('An IMAP string holds no line break nor NUL.');
        $client->login("u\r\nA9 DELETE INBOX", 'p');
    }

    public function testACommandNeedsAnOpenConnection(): void
    {
        $client = new ImapClient(static fn (): never => throw new LogicException('never opened'));
        $client->close();

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The connection to the IMAP server is not open.');
        $client->unseen();
    }

    public function testAServerThatCannotBeReachedFails(): void
    {
        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The IMAP server 127.0.0.1:1 cannot be reached');

        iterator_to_array(ImapBounceMailbox::overTls('127.0.0.1', 1, 'u', 'p', timeout: 1.0)->unread(1));
    }

    /**
     * @return array{ImapClient, resource}
     */
    private function connection(string $script, int $maxLiteral = 10_485_760): array
    {
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$client, $server] = $pair;
        $this->streams = [$client, $server];
        fwrite($server, $script);

        stream_socket_shutdown($server, \STREAM_SHUT_WR);

        return [new ImapClient(static fn () => $client, $maxLiteral, 64), $server];
    }

    /**
     * @param resource $server
     *
     * @return list<string>
     */
    private function sent(mixed $server): array
    {
        stream_set_blocking($server, false);

        return array_values(array_filter(explode("\r\n", (string) stream_get_contents($server)), static fn (string $line): bool => '' !== $line));
    }
}
