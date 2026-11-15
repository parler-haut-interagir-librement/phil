<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Generator;
use Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed;
use Phil\NewsletterBundle\Infrastructure\Imap\ImapBounceMailbox;
use Phil\NewsletterBundle\Infrastructure\Imap\ImapClient;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedImapStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The corners of the IMAP client: limits at their exact values, the bytes of a literal arriving a few at a time,
 * the responses that look like others.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapBounceMailbox::class)]
#[CoversClass(ImapFailed::class)]
final class ImapEdgesTest extends TestCase
{
    private int $sessions = 0;

    protected function setUp(): void
    {
        ScriptedImapStream::$chunk = 2;
    }

    public function testTheDefaultLimitOfALiteralIsTenMebibytes(): void
    {
        $client = $this->defaultClient("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {10485761}\r\n");
        $client->open();
        $client->login('u', 'p');

        try {
            $client->fetch(1);
            self::fail('A literal over the limit is refused.');
        } catch (ImapFailed $imapFailed) {
            self::assertSame('The server sent a literal of 10485761 bytes; at most 10485760 are read.', $imapFailed->getMessage());
        }

        $client = $this->defaultClient("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {10485760}\r\n");
        $client->open();
        $client->login('u', 'p');

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The IMAP server closed the connection inside a literal.');
        $client->fetch(1);
    }

    public function testALiteralOfTheLimitIsRead(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {16}\r\n0123456789abcdef)\r\nA2 OK\r\n", 16);
        $client->open();
        $client->login('u', 'p');

        self::assertSame('0123456789abcdef', $client->fetch(1));
    }

    public function testTheBytesOfALiteralMayComeAFewAtATime(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {5}\r\nhello)\r\nA2 OK\r\n");
        $client->open();
        $client->login('u', 'p');

        self::assertSame('hello', $client->fetch(1));
    }

    public function testASecondLiteralOfTheSameResponseIsReadWholeToo(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {3}\r\nabc X {6}\r\nA2 NO\n)\r\nA2 OK\r\n");
        $client->open();
        $client->login('u', 'p');

        self::assertSame('abc', $client->fetch(1));
    }

    public function testTheEndOfAResponseAfterItsLiteralIsReadWithIt(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\n* 1 FETCH (BODY[] {3}\r\nabcA2 NO\r\nA2 OK\r\n");
        $client->open();
        $client->login('u', 'p');

        self::assertSame('abc', $client->fetch(1));
    }

    public function testOpeningAgainClosesTheFirstConnection(): void
    {
        $streams = [];
        $scripts = ["* OK\r\nA1 OK\r\n", "* OK\r\n"];
        $client = new ImapClient(function () use (&$streams, &$scripts) {
            $stream = ScriptedImapStream::open('reopen'.\count($streams).$this->sessions++, array_shift($scripts) ?? '');
            $streams[] = $stream;

            return $stream;
        });

        $client->open();
        $client->open();

        self::assertCount(2, $streams);
        self::assertFalse(\is_resource($streams[0]));
        self::assertTrue(\is_resource($streams[1]));
        self::assertTrue($client->isOpen());
        $client->close();
    }

    public function testTheUidsOfSeveralResponsesAreAllRead(): void
    {
        $client = $this->client("* OK\r\n* SEARCH x4 4x 9 4\r\n* SEARCH 2\r\nA1 OK\r\n");
        $client->open();

        self::assertSame([2, 4, 9], $client->unseen());
    }

    public function testAResponseThatIsNotAFetchIsNotAMessage(): void
    {
        foreach (["* OK\r\n* 1 STATUS (X {3}\r\nabc)\r\nA1 OK\r\n", "* OK\r\nx * 1 FETCH (BODY[] {3}\r\nabc)\r\nA1 OK\r\n"] as $script) {
            $client = $this->client($script);
            $client->open();

            try {
                $client->fetch(1);
                self::fail('Nothing is a message.');
            } catch (ImapFailed $failed) {
                self::assertSame('The server sent no message for the UID 1.', $failed->getMessage());
            }
        }
    }

    public function testAFlagIsASystemFlagAndNothingElse(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\n");
        $client->open();
        $client->flag(7, '\\Seen');

        self::assertSame("A1 UID STORE 7 +FLAGS.SILENT (\\Seen)\r\n", $this->written());
    }

    #[DataProvider('flags')]
    public function testAFlagWithSomethingAroundItIsRefused(string $flag): void
    {
        $client = $this->client("* OK\r\n");
        $client->open();

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a system flag.', $flag));
        $client->flag(7, $flag);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function flags(): iterable
    {
        yield 'a suffix' => ['\\Seen!'];
        yield 'a prefix' => ['!\\Seen'];
        yield 'a trailing new line' => ["\\Seen\n"];
    }

    public function testTenThousandUntaggedLinesAreTooMany(): void
    {
        ScriptedImapStream::$chunk = 8192;
        $client = $this->client("* OK\r\n".str_repeat("* X\r\n", 10_000)."A1 OK\r\n");
        $client->open();

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The server sent too many lines.');
        $client->login('u', 'p');
    }

    public function testALineThatStartsLikeTheTagIsNotTheTaggedResponse(): void
    {
        $client = $this->client("* OK\r\nA1X junk\r\nA1 OK\r\n");
        $client->open();
        $client->login('u', 'p');

        self::assertStringContainsString('A1 LOGIN', $this->written());
    }

    public function testALineOfTheLimitLengthIsRead(): void
    {
        $client = $this->client('* OK'.str_repeat('x', 60)."\n", maxLine: 64);
        $client->open();

        self::assertTrue($client->isOpen());
    }

    public function testALineOneByteOverTheLimitIsRefused(): void
    {
        $client = $this->client('* OK'.str_repeat('x', 61)."\n", maxLine: 64);

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The server sent a line longer than 64 bytes.');
        $client->open();
    }

    #[DataProvider('tinyLines')]
    public function testALimitBelowOneByteReadsNothing(int $maxLine): void
    {
        $client = $this->client("* OK\r\n", maxLine: $maxLine);

        $this->expectException(ImapFailed::class);
        $this->expectExceptionMessageIsOrContains('The IMAP server closed the connection.');
        $client->open();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function tinyLines(): iterable
    {
        yield 'minus one' => [-1];
        yield 'minus two' => [-2];
    }

    public function testTheMailboxReadsOneMessageAtLeastAndNamesThemByTheirUid(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\nA2 OK\r\n* SEARCH 4 9\r\nA3 OK\r\n* 1 FETCH (BODY[] {1}\r\nx)\r\nA4 OK\r\nA5 OK\r\n");
        $mailbox = new ImapBounceMailbox($client, 'u', 'p');
        $keys = [];
        $messages = [];

        foreach ($mailbox->unread(0) as $id => $raw) {
            $keys[] = $id;
            $messages[] = $raw;
        }

        self::assertSame(['4'], $keys);
        self::assertSame(['x'], $messages);
    }

    public function testTheMailboxClosesTheConnectionWhenAReadFails(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\nA2 OK\r\n* SEARCH 4\r\nA3 OK\r\nA4 NO\r\n");
        $mailbox = new ImapBounceMailbox($client, 'u', 'p');

        try {
            iterator_to_array($mailbox->unread(5));
            self::fail('A refused fetch fails the read.');
        } catch (ImapFailed) {
            self::assertFalse($client->isOpen());
        }
    }

    public function testOnlyTheUidOfAMessageIsAcknowledged(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\nA2 OK\r\n* SEARCH 4\r\nA3 OK\r\n* 1 FETCH (BODY[] {1}\r\nx)\r\nA4 OK\r\nA5 OK\r\nA6 OK\r\n");
        $mailbox = new ImapBounceMailbox($client, 'u', 'p');

        foreach ($mailbox->unread(5) as $id => $raw) {
            foreach (['4x', 'x4', "4\n", '0', '', '04', '12345678901'] as $stray) {
                $mailbox->acknowledge($stray);
            }

            self::assertStringNotContainsString('STORE', $this->written());
            $mailbox->acknowledge((string) $id);
            self::assertStringContainsString('A5 UID STORE 4 +FLAGS.SILENT (\\Seen)', $this->written());
        }
    }

    public function testResettingClosesTheConnection(): void
    {
        $client = $this->client("* OK\r\nA1 OK\r\nA2 OK\r\n* SEARCH 4\r\nA3 OK\r\n* 1 FETCH (BODY[] {1}\r\nx)\r\nA4 OK\r\nA5 OK\r\n");
        $mailbox = new ImapBounceMailbox($client, 'u', 'p');
        $reading = $mailbox->unread(5);
        self::assertInstanceOf(Generator::class, $reading);
        $reading->current();
        self::assertTrue($client->isOpen());

        $mailbox->reset();

        self::assertFalse($client->isOpen());
    }

    public function testAMailboxOverTlsKeepsItsMessagesByDefaultAndChecksTheCertificate(): void
    {
        $mailbox = ImapBounceMailbox::overTls('imap.example.com', 993, 'u', 'p');

        self::assertFalse(new ReflectionProperty(ImapBounceMailbox::class, 'delete')->getValue($mailbox));
        self::assertTrue(new ReflectionProperty(ImapBounceMailbox::class, 'delete')->getValue(ImapBounceMailbox::overTls('imap.example.com', 993, 'u', 'p', delete: true)));
        self::assertSame('INBOX', new ReflectionProperty(ImapBounceMailbox::class, 'folder')->getValue($mailbox));
        self::assertSame(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => 'imap.example.com']], ImapBounceMailbox::tlsOptions('imap.example.com'));
    }

    private function client(string $script, int $maxLiteral = 10_485_760, int $maxLine = ImapClient::MAX_LINE): ImapClient
    {
        $name = 'session'.++$this->sessions.'x'.spl_object_id($this);
        $this->current = $name;

        return new ImapClient(static fn (): mixed => ScriptedImapStream::open($name, $script), $maxLiteral, $maxLine);
    }

    private string $current = '';

    private function defaultClient(string $script): ImapClient
    {
        $name = 'default'.++$this->sessions.'x'.spl_object_id($this);
        $this->current = $name;

        return new ImapClient(static fn (): mixed => ScriptedImapStream::open($name, $script));
    }

    private function written(): string
    {
        return ScriptedImapStream::$written[$this->current] ?? '';
    }
}
