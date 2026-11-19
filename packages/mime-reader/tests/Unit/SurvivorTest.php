<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit;

use Phil\MimeReader\Headers;
use Phil\MimeReader\HeaderValue;
use Phil\MimeReader\Internal\Bytes;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Internal\TransferDecoder;
use Phil\MimeReader\Limits;
use Phil\MimeReader\Mailbox;
use Phil\MimeReader\MimeReader;
use Phil\MimeReader\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the limits, the order of the pieces and the boundaries of the chunks that the other tests leave open.
 */
#[CoversClass(Headers::class)]
#[CoversClass(HeaderValue::class)]
#[CoversClass(Bytes::class)]
#[CoversClass(Charset::class)]
#[CoversClass(EncodedWords::class)]
#[CoversClass(TransferDecoder::class)]
#[CoversClass(MimeReader::class)]
#[CoversClass(Part::class)]
final class SurvivorTest extends TestCase
{
    public function testThirtyTwoParametersAreReadByDefault(): void
    {
        foreach ([32, 33] as $given) {
            $text = 'a/b';

            for ($i = 1; $i <= $given; ++$i) {
                $text .= '; p'.$i.'='.$i;
            }

            self::assertCount(32, HeaderValue::parse($text)->parameters, (string) $given);
            $value = new Headers([['X', $text]])->parameterized('X');
            self::assertInstanceOf(HeaderValue::class, $value);
            self::assertCount(32, $value->parameters, (string) $given);
        }
    }

    public function testASegmentThatIsSkippedDoesNotEndTheReading(): void
    {
        self::assertSame(['ok' => '2'], HeaderValue::parse('a/b; flag; bad name=1; ok=2')->parameters);
        self::assertSame(['a' => '3'], HeaderValue::parse('a/b; a=1; b=2; a=3', 1)->parameters);
    }

    public function testASectionWithoutIndexIsTheFirstOne(): void
    {
        self::assertSame(['t' => 'b'], HeaderValue::parse('a/b; t*=a; t*0=b')->parameters);
        self::assertSame(['t' => 'ab'], HeaderValue::parse('a/b; t*=a; t*1=b')->parameters);
    }

    public function testAnExtendedValueMayHoldLineBreaks(): void
    {
        self::assertSame(['t' => "x\ny"], HeaderValue::parse("a/b; t*=utf-8''x\ny")->parameters);
    }

    public function testAnEscapedQuoteKeepsItsPlace(): void
    {
        self::assertSame(['n' => 'a"'], HeaderValue::parse('a/b; n="a\""')->parameters);
        self::assertSame(['n' => '"'], HeaderValue::parse('a/b; n="')->parameters);
    }

    public function testFiveHundredFieldsAreReadByDefault(): void
    {
        foreach ([500, 501] as $given) {
            $lines = [];

            for ($i = 1; $i <= $given; ++$i) {
                $lines[] = 'H'.$i.': v';
            }

            self::assertCount(500, Headers::parse(implode("\n", $lines))->fields, (string) $given);
        }
    }

    public function testAThousandMailboxesAreReadByDefault(): void
    {
        foreach ([1000, 1001] as $given) {
            $addresses = [];

            for ($i = 1; $i <= $given; ++$i) {
                $addresses[] = 'u'.$i.'@x.y';
            }

            self::assertCount(1000, new Headers([['To', implode(', ', $addresses)]])->mailboxes('To'), (string) $given);
        }
    }

    public function testMailboxesWithoutAngleBracketsAreSplit(): void
    {
        $mailboxes = new Headers([['To', 'a@x.y, b@x.y']])->mailboxes('To');

        self::assertSame(['a@x.y', 'b@x.y'], array_map(static fn (Mailbox $mailbox): string => $mailbox->address, $mailboxes));
    }

    public function testACommentKeepsItsEscapesAndItsNesting(): void
    {
        foreach (['Bob (\\)) <b@x.y>', 'Bob (a (b) c) <b@x.y>', 'Bob (a \\( b) <b@x.y>'] as $field) {
            $mailboxes = new Headers([['To', $field]])->mailboxes('To');

            self::assertCount(1, $mailboxes, $field);
            self::assertSame('b@x.y', $mailboxes[0]->address, $field);
            self::assertSame('Bob', $mailboxes[0]->name, $field);
        }
    }

    public function testAQuotedNameKeepsItsEscapedQuote(): void
    {
        $mailboxes = new Headers([['To', '"a\\"" <x@y.z>']])->mailboxes('To');

        self::assertSame('a"', $mailboxes[0]->name);
        self::assertSame('x@y.z', $mailboxes[0]->address);
    }

    public function testASeparatorInsideAngleBracketsDoesNotSplit(): void
    {
        $mailboxes = new Headers([['To', '<a,b@x.y>']])->mailboxes('To');

        self::assertCount(1, $mailboxes);
        self::assertSame('a,b@x.y', $mailboxes[0]->address);
    }

    public function testTheAddressIsTrimmedAndTheNameUnquotedOnlyWhenQuoted(): void
    {
        $read = static fn (string $field): ?Mailbox => new Headers([['To', $field]])->mailboxes('To')[0] ?? null;

        self::assertSame('a@x.y', $read('N < a@x.y >')?->address);
        self::assertNull($read('"" <a@x.y>')?->name);
        self::assertSame('"', $read('" <a@x.y>')?->name);
        self::assertSame('a"', $read('a" <x@y.z>')?->name);
    }

    public function testAnEmptyEntryGivesNoMailbox(): void
    {
        $mailboxes = new Headers([['To', 'a@x.y,, ,b@x.y']])->mailboxes('To');

        self::assertCount(2, $mailboxes);
    }

    public function testANeedleStartingPastTheEndIsNotFound(): void
    {
        self::assertNull(Bytes::find('aaaa--bZ', '--b', 4, 3));
    }

    public function testACharsetNameIsTrimmed(): void
    {
        self::assertSame('é', Charset::toUtf8("\xE9", ' ISO-8859-1 '));
        self::assertSame('“', Charset::toUtf8("\x93", ' ISO-8859-1 '));
    }

    public function testEncodedWordsOfASameSetInAnyCaseAreJoined(): void
    {
        self::assertSame('é', EncodedWords::decode('=?UTF-8?Q?=C3?= =?utf-8?Q?=A9?='));
        self::assertSame(' a', EncodedWords::decode(' =?utf-8?Q?a?='));
        self::assertSame('x a y b', EncodedWords::decode('x =?utf-8?Q?a?= y =?utf-8?Q?b?='));
    }

    public function testNoChunkIsEmpty(): void
    {
        self::assertSame([], $this->chunks('abc', '', 3, 0));
        self::assertSame([65_536], $this->chunks(str_repeat('a', 65_536), '', 0, 65_536));
    }

    public function testAnEscapeEndingAChunkIsDecodedWithIt(): void
    {
        self::assertSame([65_534, 1], $this->chunks(str_repeat('a', 65_533).'=41b', 'quoted-printable'));
        self::assertSame([65_534, 2], $this->chunks('=41'.str_repeat('a', 65_533).'bb', 'quoted-printable'));
    }

    public function testAnEscapeSplitByTheChunkIsCarriedOver(): void
    {
        self::assertSame([65_535, 1], $this->chunks(str_repeat('a', 65_535).'=Bb', 'quoted-printable'));
    }

    public function testTheStreamIsReadOneOctetBeyondTheLimit(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat('a', 20));
        rewind($stream);

        $part = new MimeReader(new Limits(maxBytes: 10))->readStream($stream);

        self::assertTrue($part->incomplete);
        self::assertSame(11, ftell($stream));
    }

    public function testTypeAndEncodingAreReadWithoutCase(): void
    {
        $part = new MimeReader()->read("Content-Type: Text/HTML\nContent-Transfer-Encoding: BASE64\n\naGk=");

        self::assertSame('text/html', $part->type);
        self::assertSame('base64', $part->encoding);
        self::assertSame('hi', $part->content());
    }

    public function testHeldMessagesAreCutAtTheDepthLimit(): void
    {
        $raw = str_repeat("Content-Type: message/rfc822\n\n", 4)."Subject: x\n\nbody";
        $reader = new MimeReader(new Limits(maxDepth: 3));

        $levels = iterator_to_array($reader->read($raw)->walk(true), false);

        self::assertCount(3, $levels);
        self::assertSame([false, false, true], array_map(static fn (Part $part): bool => $part->incomplete, $levels));

        $whole = iterator_to_array(new MimeReader()->read($raw)->walk(true), false);

        self::assertCount(5, $whole);
        self::assertSame([false, false, false, false, false], array_map(static fn (Part $part): bool => $part->incomplete, $whole));
        self::assertSame('text/plain', $whole[4]->type);
    }

    public function testAnEncodedHeldMessageStopsAtItsPart(): void
    {
        $held = base64_encode("Subject: x\n\nhello!");
        $raw = "Content-Type: multipart/mixed; boundary=b\n\n--b\nContent-Type: message/rfc822\nContent-Transfer-Encoding: base64\n\n".$held."\n--b\nContent-Type: text/plain\n\ntail\n--b--\n";

        $message = new MimeReader()->read($raw);

        self::assertSame('hello!', $message->parts[0]->message?->textBody());
        self::assertSame('tail', rtrim($message->parts[1]->text()));
    }

    public function testAMultipartWithoutAnyDelimiterHasNoPart(): void
    {
        $message = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\n\nno delimiter here\n");

        self::assertSame([], $message->parts);
    }

    public function testAFileNameIsCutAt255Octets(): void
    {
        $message = new MimeReader()->read('Content-Disposition: attachment; filename="'.str_repeat('a', 300)."\"\n\nx");

        self::assertSame(255, \strlen((string) $message->filename()));
    }

    public function testWalkingIntoMessagesGoesDownEveryLevel(): void
    {
        $raw = "Content-Type: message/rfc822\n\nContent-Type: message/rfc822\n\nSubject: x\n\nbody";
        $types = array_map(static fn (Part $part): string => $part->type, iterator_to_array(new MimeReader()->read($raw)->walk(true), false));

        self::assertSame(['message/rfc822', 'message/rfc822', 'text/plain'], $types);
    }

    public function testEveryAttachmentIsListed(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\n\n--b\nContent-Disposition: attachment; filename=a\n\n1\n--b\nContent-Disposition: attachment; filename=b\n\n2\n--b--\n";

        self::assertCount(2, new MimeReader()->read($raw)->attachments());
    }

    public function testAThousandGroupsOfFiveHundredFieldsAreReadByDefault(): void
    {
        foreach ([1000, 1001] as $given) {
            $groups = [];

            for ($i = 1; $i <= $given; ++$i) {
                $groups[] = 'A: '.$i;
            }

            $part = Part::create(new Headers(), 'message/delivery-status', [], implode("\n\n", $groups));

            self::assertCount(1000, $part->fieldGroups(), (string) $given);
        }

        foreach ([500, 501] as $given) {
            $lines = [];

            for ($i = 1; $i <= $given; ++$i) {
                $lines[] = 'F'.$i.': v';
            }

            $part = Part::create(new Headers(), 'message/delivery-status', [], implode("\n", $lines));

            self::assertCount(500, $part->fieldGroups()[0]->fields, (string) $given);
        }
    }

    /**
     * @return list<int>
     */
    private function chunks(string $source, string $encoding, int $offset = 0, ?int $length = null): array
    {
        $sizes = [];
        TransferDecoder::decode($source, $offset, $length ?? \strlen($source), $encoding, static function (string $chunk) use (&$sizes): void {
            $sizes[] = \strlen($chunk);
        });

        return $sizes;
    }
}
