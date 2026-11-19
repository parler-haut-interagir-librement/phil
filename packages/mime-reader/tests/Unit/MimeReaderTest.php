<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit;

use Phil\MimeReader\Exception\InvalidLimits;
use Phil\MimeReader\Exception\InvalidStream;
use Phil\MimeReader\Headers;
use Phil\MimeReader\HeaderValue;
use Phil\MimeReader\Internal\Bytes;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Internal\PartBudget;
use Phil\MimeReader\Internal\TransferDecoder;
use Phil\MimeReader\Limits;
use Phil\MimeReader\MimeReader;
use Phil\MimeReader\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeReader::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Part::class)]
#[CoversClass(PartBudget::class)]
#[CoversClass(Bytes::class)]
#[CoversClass(Headers::class)]
#[CoversClass(HeaderValue::class)]
#[CoversClass(TransferDecoder::class)]
#[CoversClass(Charset::class)]
#[CoversClass(EncodedWords::class)]
final class MimeReaderTest extends TestCase
{
    public function testANestedMessageIsReadPartByPart(): void
    {
        $message = new MimeReader()->read($this->fixture('nested.eml'));

        self::assertSame('multipart/mixed', $message->type);
        self::assertSame('Rapport de la semaine', $message->headers->decoded('Subject'));
        self::assertFalse($message->incomplete);
        self::assertSame(
            ['multipart/mixed', 'multipart/alternative', 'text/plain', 'multipart/related', 'text/html', 'image/png', 'application/pdf', 'message/rfc822'],
            $this->types($message),
        );
        self::assertSame(
            ['multipart/mixed', 'multipart/alternative', 'text/plain', 'multipart/related', 'text/html', 'image/png', 'application/pdf', 'message/rfc822', 'text/plain'],
            $this->types($message, true),
        );

        [$alternative, $pdf, $held] = $message->parts;
        self::assertSame('Café crème, soft break.', $alternative->parts[0]->text());
        self::assertSame('quoted-printable', $alternative->parts[0]->encoding);
        self::assertSame('<p>Café</p><img src="cid:logo">', $alternative->parts[1]->parts[0]->content());
        self::assertSame("\x89PNG\r\n\x1a\n", $alternative->parts[1]->parts[1]->content());
        self::assertSame('%PDF-1.4'."\n", $pdf->content());
        self::assertSame('résume.pdf', $pdf->filename());
        self::assertSame('attachment', $pdf->disposition());
        self::assertSame([], $held->parts);
        self::assertInstanceOf(Part::class, $held->message);
        self::assertSame('Inner', $held->message->headers->get('Subject'));
        self::assertSame('Inner body.', $held->message->content());

        self::assertSame('Café crème, soft break.', $message->textBody());
        self::assertSame('<p>Café</p><img src="cid:logo">', $message->htmlBody());
        self::assertSame([$pdf], $message->attachments());
        self::assertFalse($alternative->parts[1]->parts[1]->isAttachment());
    }

    public function testADeliveryReportGivesItsFieldGroups(): void
    {
        $message = new MimeReader()->read($this->fixture('report.eml'));

        self::assertSame('delivery-status', $message->parameters['report-type']);
        $status = $message->find('message/delivery-status');
        self::assertInstanceOf(Part::class, $status);
        $groups = $status->fieldGroups();
        self::assertCount(3, $groups);
        self::assertSame('dns; mx.example.org', $groups[0]->get('Reporting-MTA'));
        self::assertSame('5.1.1', $groups[1]->get('Status'));
        self::assertSame('delayed', $groups[2]->get('Action'));
        self::assertCount(2, $status->fieldGroups(2));
        self::assertSame([['Final-Recipient', 'rfc822; gone@example.com']], $status->fieldGroups(fields: 1)[1]->fields);

        $headers = $message->find('text/rfc822-headers');
        self::assertInstanceOf(Part::class, $headers);
        self::assertSame('<abc@example.net>', $headers->fieldGroups()[0]->get('Message-ID'));
    }

    public function testADigestHoldsMessagesByDefault(): void
    {
        $message = new MimeReader()->read("Content-Type: multipart/digest; boundary=d\n\n--d\n\nSubject: one\n\nfirst\n--d\nContent-Type: text/plain\n\nplain\n--d--\n");

        self::assertSame(['multipart/digest', 'message/rfc822', 'text/plain'], $this->types($message));
        self::assertSame('one', $message->parts[0]->message?->headers->get('Subject'));
        self::assertSame('first', $message->parts[0]->message->content());
    }

    public function testAMessageWithoutHeadersOrBodyIsKept(): void
    {
        $reader = new MimeReader();

        $noHeaders = $reader->read("\nbody only");
        self::assertSame([], $noHeaders->headers->fields);
        self::assertSame('body only', $noHeaders->content());
        self::assertSame('text/plain', $noHeaders->type);

        $crlfNoHeaders = $reader->read("\r\nbody");
        self::assertSame('body', $crlfNoHeaders->content());

        $headersOnly = $reader->read("Subject: alone\nX-Other: yes");
        self::assertSame('alone', $headersOnly->headers->get('Subject'));
        self::assertSame('yes', $headersOnly->headers->get('X-Other'));
        self::assertSame('', $headersOnly->content());

        $empty = $reader->read('');
        self::assertSame('', $empty->content());
        self::assertSame([], $empty->headers->fields);
    }

    public function testTheFirstEmptyLineEndsTheHeadersWhateverItsLineBreak(): void
    {
        $reader = new MimeReader();

        self::assertSame("b\r\n\r\nc", $reader->read("A: 1\r\n\r\nb\r\n\r\nc")->content());
        self::assertSame("b\n\nc", $reader->read("A: 1\n\nb\n\nc")->content());
        // An LF blank line before a CRLF one ends the headers first.
        self::assertSame("x\r\n\r\ny", $reader->read("A: 1\n\nx\r\n\r\ny")->content());
        self::assertSame("y\n\nz", $reader->read("A: 1\r\n\r\ny\n\nz")->content());
        self::assertSame(['A', '1'], $reader->read("A: 1\r\n\r\ny")->headers->fields[0]);
    }

    public function testAnInvalidTypeTakesTheDefaultOfItsPlace(): void
    {
        $reader = new MimeReader();

        self::assertSame('text/plain', $reader->read("Content-Type: not a type\n\nx")->type);
        self::assertSame('text/plain', $reader->read("Content-Type: text/\n\nx")->type);
        self::assertSame('text/plain', $reader->read("Content-Type: text/plain/x\n\nx")->type);
        self::assertSame('text/plain', $reader->read("Content-Type: Text/Plain ; charset=x\n\nx")->type);
        self::assertSame('x', $reader->read("Content-Type: Text/Plain ; charset=x\n\nx")->charset());
        self::assertSame('application/vnd.a+b', $reader->read("Content-Type: application/vnd.a+b\n\nx")->type);
        self::assertSame('a/b', $reader->read("Content-Type: a/b\n\nx")->type);
    }

    public function testAMultipartWithoutAUsableBoundaryIsALeaf(): void
    {
        $reader = new MimeReader();

        $none = $reader->read("Content-Type: multipart/mixed\n\n--b\n\nx\n--b--");
        self::assertSame([], $none->parts);
        self::assertSame("--b\n\nx\n--b--", $none->content());

        self::assertSame([], $reader->read("Content-Type: multipart/mixed; boundary=\"\"\n\n--\n\nx")->parts);
        self::assertSame([], $reader->read('Content-Type: multipart/mixed; boundary='.str_repeat('b', 201)."\n\n--b\n\nx")->parts);
        self::assertCount(1, $reader->read('Content-Type: multipart/mixed; boundary='.str_repeat('b', 200)."\n\n--".str_repeat('b', 200)."\n\nx")->parts);
        self::assertSame([], $reader->read("Content-Type: multipart/mixed; boundary=\"a\tb\"\n\n--a\tb\n\nx")->parts);
        self::assertCount(1, $reader->read("Content-Type: multipart/mixed; boundary=\"a b\"\n\n--a b\n\nx")->parts);
    }

    public function testADelimiterCountsOnlyAtTheStartOfALineAndAlone(): void
    {
        $message = new MimeReader()->read(
            "Content-Type: multipart/mixed; boundary=b\n\npreamble --b inside\n--bb\n--b  \n\none --b\n--b-x\nstill one\n--b\t\r\n\r\ntwo\r\n--b--   \nepilogue\n--b\n\nnever",
        );

        self::assertCount(2, $message->parts);
        self::assertSame("one --b\n--b-x\nstill one", $message->parts[0]->content());
        self::assertSame('two', $message->parts[1]->content());
    }

    public function testAMultipartWithoutClosingLineEndsWithItsText(): void
    {
        $message = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\n\n--b\n\none\n--b\n\ntwo\n");

        self::assertCount(2, $message->parts);
        self::assertSame("two\n", $message->parts[1]->content());
        self::assertFalse($message->incomplete);
    }

    public function testADelimiterRightAfterAnotherGivesAnEmptyPart(): void
    {
        $message = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\n\n--b\n--b\n\nx\n--b--");

        self::assertCount(2, $message->parts);
        self::assertSame('', $message->parts[0]->content());
        self::assertSame([], $message->parts[0]->headers->fields);
        self::assertSame('x', $message->parts[1]->content());
    }

    public function testADelimiterAtTheVeryStartOfTheBodyOpensTheFirstPart(): void
    {
        $message = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\n\n--b\nContent-Type: text/html\n\n<b>x</b>\r\n--b--");

        self::assertSame('text/html', $message->parts[0]->type);
        self::assertSame('<b>x</b>', $message->parts[0]->content());
    }

    public function testAnEncodedContainerIsDecodedBeforeItsPartsAreRead(): void
    {
        $inner = "Subject: wrapped\n\nhello";
        $message = new MimeReader()->read("Content-Type: message/rfc822\nContent-Transfer-Encoding: base64\n\n".chunk_split(base64_encode($inner), 8, "\n"));

        self::assertSame('wrapped', $message->message?->headers->get('Subject'));
        self::assertSame('hello', $message->message->content());

        $multipart = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\nContent-Transfer-Encoding: quoted-printable\n\n--b\n\nCaf=C3=A9\n--b--");
        self::assertSame('Café', $multipart->parts[0]->content());

        $plain = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\nContent-Transfer-Encoding: 8bit\n\n--b\n\nCaf=C3=A9\n--b--");
        self::assertSame('Caf=C3=A9', $plain->parts[0]->content());
    }

    public function testTheOctetsBeyondTheLimitAreNotRead(): void
    {
        $message = new MimeReader(new Limits(maxBytes: 12))->read("Subject: abcdefgh\n\nbody");

        self::assertTrue($message->incomplete);
        self::assertSame('abc', $message->headers->get('Subject'));
        self::assertSame('', $message->content());

        $exact = new MimeReader(new Limits(maxBytes: 7))->read("A: b\n\nc");
        self::assertFalse($exact->incomplete);
        self::assertSame('c', $exact->content());

        $over = new MimeReader(new Limits(maxBytes: 6))->read("A: b\n\nc");
        self::assertTrue($over->incomplete);
    }

    public function testTheDepthLimitLeavesTheDeepestContainerUnread(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=a\n\n--a\nContent-Type: multipart/mixed; boundary=b\n\n--b\n\ndeep\n--b--\n--a--";

        $shallow = new MimeReader(new Limits(maxDepth: 2))->read($raw);
        self::assertSame(['multipart/mixed', 'multipart/mixed'], $this->types($shallow));
        self::assertFalse($shallow->incomplete);
        self::assertTrue($shallow->parts[0]->incomplete);
        self::assertSame("--b\n\ndeep\n--b--", $shallow->parts[0]->content());

        $deep = new MimeReader(new Limits(maxDepth: 3))->read($raw);
        self::assertSame(['multipart/mixed', 'multipart/mixed', 'text/plain'], $this->types($deep));
        self::assertFalse($deep->parts[0]->incomplete);

        $single = new MimeReader(new Limits(maxDepth: 1))->read($raw);
        self::assertSame([], $single->parts);
        self::assertTrue($single->incomplete);

        $held = new MimeReader(new Limits(maxDepth: 1))->read("Content-Type: message/rfc822\n\nSubject: x\n\ny");
        self::assertNull($held->message);
        self::assertTrue($held->incomplete);
    }

    public function testThePartLimitStopsTheReading(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\n\n".str_repeat("--b\n\nx\n", 10).'--b--';

        $cut = new MimeReader(new Limits(maxParts: 4))->read($raw);
        self::assertCount(3, $cut->parts);
        self::assertTrue($cut->incomplete);

        $whole = new MimeReader(new Limits(maxParts: 11))->read($raw);
        self::assertCount(10, $whole->parts);
        self::assertFalse($whole->incomplete);

        $none = new MimeReader(new Limits(maxParts: 1))->read($raw);
        self::assertSame([], $none->parts);
        self::assertTrue($none->incomplete);

        $held = new MimeReader(new Limits(maxParts: 1))->read("Content-Type: message/rfc822\n\nSubject: x\n\ny");
        self::assertNull($held->message);
        self::assertTrue($held->incomplete);

        $budget = new MimeReader(new Limits(maxParts: 2))->read("Content-Type: message/rfc822\n\nSubject: x\n\ny");
        self::assertSame('x', $budget->message?->headers->get('Subject'));
        self::assertFalse($budget->incomplete);
    }

    public function testThePartLimitCountsNestedParts(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=a\n\n--a\nContent-Type: multipart/mixed; boundary=b\n\n--b\n\n1\n--b\n\n2\n--b--\n--a\n\n3\n--a--";

        $message = new MimeReader(new Limits(maxParts: 4))->read($raw);

        self::assertSame(['multipart/mixed', 'multipart/mixed', 'text/plain', 'text/plain'], $this->types($message));
        self::assertTrue($message->incomplete);
        self::assertFalse($message->parts[0]->incomplete);
    }

    public function testTheHeaderAndParameterLimitsApplyToEachPart(): void
    {
        $message = new MimeReader(new Limits(maxHeaders: 2, maxParameters: 1))->read("A: 1\nB: 2\nContent-Type: text/plain; charset=utf-8\n\nx");
        self::assertCount(2, $message->headers->fields);
        self::assertSame('text/plain', $message->type);

        $parameters = new MimeReader(new Limits(maxParameters: 1))->read("Content-Type: text/plain; charset=utf-8; format=flowed\n\nx");
        self::assertSame(['charset' => 'utf-8'], $parameters->parameters);
    }

    public function testAMessageIsReadFromAStream(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, "Subject: streamed\n\nbody");
        rewind($stream);

        self::assertSame('streamed', new MimeReader()->readStream($stream)->headers->get('Subject'));

        fwrite($stream, 'ignored');
        rewind($stream);
        $cut = new MimeReader(new Limits(maxBytes: 10))->readStream($stream);
        self::assertTrue($cut->incomplete);
        self::assertSame('s', $cut->headers->get('Subject'));
        fclose($stream);
    }

    public function testOnlyAnOpenStreamIsRead(): void
    {
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(InvalidStream::class);
        new MimeReader()->readStream($stream);
    }

    public function testAStringIsNoStream(): void
    {
        $this->expectException(InvalidStream::class);
        new MimeReader()->readStream('Subject: x');
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function invalidLimits(): iterable
    {
        foreach (['maxBytes', 'maxDepth', 'maxParts', 'maxHeaders', 'maxParameters'] as $name) {
            yield $name => [[$name => 0]];
        }
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('invalidLimits')]
    public function testEveryLimitIsAtLeastOne(array $arguments): void
    {
        $this->expectException(InvalidLimits::class);
        new Limits(...$arguments);
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame([26_214_400, 12, 256, 500, 32], [$limits->maxBytes, $limits->maxDepth, $limits->maxParts, $limits->maxHeaders, $limits->maxParameters]);
        self::assertSame(1, new Limits(1, 1, 1, 1, 1)->maxBytes);
    }

    public function testTheBudgetNeverGoesBelowZero(): void
    {
        $budget = new PartBudget(1);
        $budget->take();
        $budget->take();

        self::assertSame(0, $budget->left());
        self::assertFalse($budget->available());
        self::assertTrue(new PartBudget(1)->available());
        self::assertSame(1, new PartBudget(1)->left());
    }

    public function testAHostileMessageCostsTimeBoundedByTheLimits(): void
    {
        $start = hrtime(true);
        $parts = new MimeReader()->read("Content-Type: multipart/mixed; boundary=b\n\n".str_repeat("--b\n\nx\n", 50_000).'--b--');
        $nested = new MimeReader()->read(implode('', array_map(static fn (int $i): string => "Content-Type: multipart/mixed; boundary=b{$i}\n\n--b{$i}\n", range(1, 1_000))).str_repeat('x', 100_000));
        $elapsed = (hrtime(true) - $start) / 1e9;

        self::assertCount(255, $parts->parts);
        self::assertTrue($parts->incomplete);
        self::assertCount(12, iterator_to_array($nested->walk(), false));
        self::assertLessThan(5.0, $elapsed);
    }

    /**
     * @return list<string>
     */
    private function types(Part $message, bool $intoMessages = false): array
    {
        $types = [];

        foreach ($message->walk($intoMessages) as $part) {
            $types[] = $part->type;
        }

        return $types;
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(\dirname(__DIR__).'/Fixtures/'.$name);
    }
}
