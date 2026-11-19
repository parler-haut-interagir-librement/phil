<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit;

use Phil\MimeReader\Exception\InvalidStream;
use Phil\MimeReader\Headers;
use Phil\MimeReader\HeaderValue;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Internal\TransferDecoder;
use Phil\MimeReader\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Part::class)]
#[CoversClass(Headers::class)]
#[CoversClass(HeaderValue::class)]
#[CoversClass(TransferDecoder::class)]
#[CoversClass(Charset::class)]
#[CoversClass(EncodedWords::class)]
final class PartTest extends TestCase
{
    public function testAPartIsBuiltFromDecodedValues(): void
    {
        $child = Part::create(new Headers(), 'TEXT/HTML', ['Charset' => 'utf-8'], '<p>x</p>');
        $message = Part::create(new Headers([['Subject', 'held']]));
        $part = Part::create(new Headers([['X', '1']]), 'multipart/mixed', [], 'raw', [$child], $message);

        self::assertSame('text/html', $child->type);
        self::assertSame(['charset' => 'utf-8'], $child->parameters);
        self::assertSame('utf-8', $child->charset());
        self::assertSame('<p>x</p>', $child->content());
        self::assertSame('', $child->encoding);
        self::assertFalse($child->incomplete);
        self::assertSame('raw', $part->content());
        self::assertSame([$child], $part->parts);
        self::assertSame($message, $part->message);
        self::assertTrue($part->isMultipart());
        self::assertFalse($child->isMultipart());

        $default = Part::create(new Headers());
        self::assertSame('text/plain', $default->type);
        self::assertSame([], $default->parameters);
        self::assertSame('', $default->content());
        self::assertSame([], $default->parts);
        self::assertNull($default->message);
        self::assertNull($default->charset());
    }

    public function testTextIsConvertedFromItsCharacterSet(): void
    {
        self::assertSame('café', Part::create(new Headers(), parameters: ['charset' => 'iso-8859-1'], body: "caf\xE9")->text());
        self::assertSame('café', Part::create(new Headers(), parameters: ['charset' => 'UTF-8'], body: 'café')->text());
        self::assertSame('caf?', Part::create(new Headers(), body: "caf\xE9")->text());
    }

    public function testTheBodyIsWrittenToAStream(): void
    {
        $part = $this->encoded('base64', base64_encode(str_repeat('abc', 50_000)));
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        self::assertSame(150_000, $part->writeTo($stream));
        rewind($stream);
        self::assertSame(str_repeat('abc', 50_000), stream_get_contents($stream));

        $temporary = $part->stream();
        self::assertSame(0, ftell($temporary));
        self::assertSame(str_repeat('abc', 50_000), stream_get_contents($temporary));
        self::assertSame('php://temp/maxmemory:2097152', stream_get_meta_data($temporary)['uri'] ?? null);
    }

    public function testAnEmptyBodyWritesNothing(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        self::assertSame(0, Part::create(new Headers())->writeTo($stream));
    }

    public function testOnlyAnOpenStreamIsWrittenTo(): void
    {
        $this->expectException(InvalidStream::class);
        Part::create(new Headers())->writeTo('php://memory');
    }

    public function testAClosedStreamIsRefused(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(InvalidStream::class);
        Part::create(new Headers())->writeTo($stream);
    }

    public function testTheDispositionIsReadInLowercase(): void
    {
        self::assertSame('attachment', $this->disposed('ATTACHMENT; filename=a')->disposition());
        self::assertNull($this->disposed('; filename=a')->disposition());
        self::assertNull(Part::create(new Headers())->disposition());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function filenames(): iterable
    {
        yield 'plain' => ['attachment; filename="report.pdf"', 'report.pdf'];
        yield 'a path' => ['attachment; filename="../../etc/passwd"', 'passwd'];
        yield 'a windows path' => ['attachment; filename="C:\\\\Users\\\\x\\\\evil.exe"', 'evil.exe'];
        yield 'control characters' => ["attachment; filename=\"a\x01b\x7Fc.txt\"", 'abc.txt'];
        yield 'leading dots and spaces' => ['attachment; filename=" ..hidden. "', 'hidden'];
        yield 'only dots' => ['attachment; filename=".."', null];
        yield 'a directory alone' => ['attachment; filename="dir/"', null];
        yield 'none' => ['attachment', null];
    }

    #[DataProvider('filenames')]
    public function testTheFilenameIsNeverAPath(string $disposition, ?string $expected): void
    {
        self::assertSame($expected, $this->disposed($disposition)->filename());
    }

    public function testTheFilenameIsCutTo255OctetsOnACharacter(): void
    {
        $name = $this->disposed('attachment; filename="'.str_repeat('é', 200).'"')->filename();

        self::assertSame(str_repeat('é', 127), $name);
    }

    public function testTheNameOfTheTypeIsTheFallbackFilename(): void
    {
        self::assertSame('a.txt', Part::create(new Headers(), parameters: ['name' => 'a.txt'])->filename());
        self::assertSame('b.txt', Part::create(new Headers([['Content-Disposition', 'attachment; filename=b.txt']]), parameters: ['name' => 'a.txt'])->filename());
        self::assertSame('a.txt', Part::create(new Headers([['Content-Disposition', 'attachment']]), parameters: ['name' => 'a.txt'])->filename());
    }

    public function testWhatMakesAnAttachment(): void
    {
        self::assertTrue($this->disposed('attachment')->isAttachment());
        self::assertTrue($this->disposed('Attachment')->isAttachment());
        self::assertFalse($this->disposed('inline; filename=a.png')->isAttachment());
        self::assertTrue(Part::create(new Headers(), parameters: ['name' => 'a.png'])->isAttachment());
        self::assertTrue($this->disposed('weird; filename=a.png')->isAttachment());
        self::assertFalse(Part::create(new Headers())->isAttachment());
        self::assertFalse(Part::create(new Headers(), 'multipart/mixed', ['name' => 'a'])->isAttachment());
        self::assertTrue(Part::create(new Headers([['Content-Disposition', 'attachment']]), 'multipart/mixed')->isAttachment());
    }

    public function testWalkingEntersHeldMessagesOnlyWhenAsked(): void
    {
        $inner = Part::create(new Headers(), 'text/html');
        $held = Part::create(new Headers(), 'message/rfc822', message: Part::create(new Headers(), 'multipart/mixed', parts: [$inner]));
        $root = Part::create(new Headers(), 'multipart/mixed', parts: [Part::create(new Headers()), $held]);

        self::assertCount(3, iterator_to_array($root->walk(), false));
        self::assertCount(5, iterator_to_array($root->walk(true), false));
        self::assertNull($root->find('text/html'));
        self::assertSame($held, $root->find('MESSAGE/RFC822'));
        self::assertSame($root, $root->find('multipart/mixed'));
        self::assertNull($root->htmlBody());
    }

    public function testTheBodiesSkipAttachments(): void
    {
        $attached = Part::create(new Headers([['Content-Disposition', 'attachment']]), body: 'attached');
        $text = Part::create(new Headers(), body: 'text');
        $html = Part::create(new Headers(), 'text/html', body: '<b>html</b>');
        $root = Part::create(new Headers(), 'multipart/mixed', parts: [$attached, $text, $html]);

        self::assertSame('text', $root->textBody());
        self::assertSame('<b>html</b>', $root->htmlBody());
        self::assertSame([$attached], $root->attachments());
        self::assertNull(Part::create(new Headers(), 'image/png')->textBody());
    }

    public function testFieldGroupsSkipEmptyGroups(): void
    {
        $part = Part::create(new Headers(), 'message/delivery-status', body: "\nA: 1\r\n \r\n\r\nnot a field\n\t\nB: 2\nC: 3\n\n");

        $groups = $part->fieldGroups();
        self::assertCount(2, $groups);
        self::assertSame([['A', '1']], $groups[0]->fields);
        self::assertSame([['B', '2'], ['C', '3']], $groups[1]->fields);
        self::assertCount(1, $part->fieldGroups(1));
        self::assertSame([['B', '2']], $part->fieldGroups(fields: 1)[1]->fields);
        self::assertSame([], Part::create(new Headers())->fieldGroups());
    }

    private function encoded(string $encoding, string $body): Part
    {
        return Part::read(new Headers(), 'application/octet-stream', [], $encoding, "x\n".$body."\ny", 2, \strlen($body), [], null, false);
    }

    private function disposed(string $disposition): Part
    {
        return Part::create(new Headers([['Content-Disposition', $disposition]]), 'application/octet-stream');
    }
}
