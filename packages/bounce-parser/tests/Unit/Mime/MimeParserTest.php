<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit\Mime;

use Phil\BounceParser\Exception\InvalidLimits;
use Phil\BounceParser\Limits;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\MimeParser;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Mime\PartBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeParser::class)]
#[CoversClass(Part::class)]
#[CoversClass(PartBudget::class)]
#[CoversClass(Limits::class)]
final class MimeParserTest extends TestCase
{
    public function testReadsASimpleMessage(): void
    {
        $part = new MimeParser()->parse("Subject: Hi\r\n\r\nLine 1\r\nLine 2");

        self::assertSame('Hi', $part->headers->get('Subject'));
        self::assertSame('text/plain', $part->type);
        self::assertSame([], $part->parameters);
        self::assertSame("Line 1\r\nLine 2", $part->body);
        self::assertSame([], $part->parts);
        self::assertNull($part->message);
    }

    public function testKeepsTheBodyOutOfTheHeaderFields(): void
    {
        $part = new MimeParser()->parse("Subject: Hi\n\nX-Body: 1");

        self::assertFalse($part->headers->has('X-Body'));
        self::assertSame([['Subject', 'Hi']], $part->headers->fields);
    }

    public function testCountsTheDepthOfNestedMultiparts(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=a\n\n--a\nContent-Type: multipart/mixed; boundary=b\n\n--b\n\ninner\n--b--\n--a--";

        self::assertSame([], new MimeParser(new Limits(maxDepth: 2))->parse($raw)->parts[0]->parts);
        self::assertSame('inner', new MimeParser(new Limits(maxDepth: 3))->parse($raw)->parts[0]->parts[0]->body);
    }

    public function testWalksIntoTheMessagesOfMessages(): void
    {
        $part = new MimeParser()->parse("Content-Type: message/rfc822\n\nContent-Type: message/rfc822\n\nSubject: deep\n\nx");

        self::assertCount(3, iterator_to_array($part->walk(true), false));
        self::assertCount(1, iterator_to_array($part->walk(), false));
    }

    public function testGivesTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame([1_048_576, 8, 64, 500, 65_536], [$limits->maxBytes, $limits->maxDepth, $limits->maxParts, $limits->maxHeaders, $limits->maxText]);
    }

    public function testReadsAMessageWithoutBody(): void
    {
        $part = new MimeParser()->parse("Subject: Hi\nFrom: a");

        self::assertSame('a', $part->headers->get('From'));
        self::assertSame('', $part->body);
        self::assertSame('', new MimeParser()->parse('')->body);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function contentTypes(): iterable
    {
        yield 'parameters' => ['Text/HTML; Charset=UTF-8; format=flowed', 'text/html', ['charset' => 'UTF-8', 'format' => 'flowed']];
        yield 'quoted' => ['multipart/mixed; boundary="a;b\\"c"; x="y"', 'multipart/mixed', ['boundary' => 'a;b"c', 'x' => 'y']];
        yield 'escape at the end' => ['text/plain; x="a\\', 'text/plain', ['x' => '"a\\']];
        yield 'segment without value' => ['text/plain; flowed; x=1', 'text/plain', ['x' => '1']];
        yield 'spaces' => [' text/html ;  x = 1 ', 'text/html', ['x' => '1']];
        yield 'escaped backslash' => ['text/plain; x="a\\\\b"', 'text/plain', ['x' => 'a\\b']];
        yield 'escaped letter' => ['text/plain; x="a\\b"', 'text/plain', ['x' => 'ab']];
        yield 'invalid type' => ['nonsense', 'text/plain', []];
        yield 'type with a space' => ['text /plain', 'text/plain', []];
        yield 'empty quoted value' => ['text/plain; x=""', 'text/plain', ['x' => '']];
        yield 'one quote' => ['text/plain; x="', 'text/plain', ['x' => '"']];
    }

    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('contentTypes')]
    public function testReadsTheContentType(string $value, string $type, array $parameters): void
    {
        $part = new MimeParser()->parse('Content-Type: '.$value."\n\nx");

        self::assertSame($type, $part->type);
        self::assertSame($parameters, $part->parameters);
    }

    public function testUndoesTheTransferEncodings(): void
    {
        self::assertSame('Élise', new MimeParser()->parse("Content-Transfer-Encoding: BASE64\n\nw4ls\naXNl\n")->body);
        self::assertSame('Élise', new MimeParser()->parse("Content-Transfer-Encoding: base64\n\nw4ls*aXNl")->body);
        self::assertSame('Élise long line', new MimeParser()->parse("Content-Transfer-Encoding: Quoted-Printable\n\n=C3=89lise long=\n line")->body);
        self::assertSame('=C3=89', new MimeParser()->parse("Content-Transfer-Encoding: 8bit\n\n=C3=89")->body);
    }

    public function testSplitsAMultipart(): void
    {
        $part = new MimeParser()->parse(<<<'EML'
            Content-Type: multipart/mixed; boundary="xyz"

            preamble
            --xyz
            Content-Type: text/plain

            one
            --xyz  
            Content-Type: text/html

            <p>two</p>

            --xyz--
            epilogue
            --xyz
            ignored
            EML);

        self::assertSame('multipart/mixed', $part->type);
        self::assertCount(2, $part->parts);
        self::assertSame('one', $part->parts[0]->body);
        self::assertSame('text/html', $part->parts[1]->type);
        self::assertSame("<p>two</p>\n", $part->parts[1]->body);
    }

    public function testEndsAMultipartWithoutClosingLineWithTheText(): void
    {
        $part = new MimeParser()->parse("Content-Type: multipart/mixed; boundary=b\n\n--b\n\none\n--b\n\ntwo");

        self::assertSame(['one', 'two'], array_map(static fn (Part $child): string => $child->body, $part->parts));
        self::assertSame([], new MimeParser()->parse("Content-Type: multipart/mixed; boundary=b\n\nno boundary here")->parts);
        self::assertSame([], new MimeParser()->parse("Content-Type: multipart/mixed; boundary=\"\"\n\n--\n\nx")->parts);
    }

    public function testReadsTheMessageAPartHolds(): void
    {
        $part = new MimeParser()->parse("Content-Type: multipart/mixed; boundary=b\n\n--b\nContent-Type: message/rfc822\n\nSubject: Inner\nContent-Type: multipart/alternative; boundary=c\n\n--c\n\ninner text\n--c--\n--b\nContent-Type: Message/Global\n\nSubject: Global\n\nbody\n--b--");
        $inner = $part->parts[0]->message;

        self::assertNotNull($inner);
        self::assertSame('Inner', $inner->headers->get('Subject'));
        self::assertSame('inner text', $inner->parts[0]->body);
        self::assertSame('Global', $part->parts[1]->message?->headers->get('Subject'));
        self::assertSame([$part, $part->parts[0], $part->parts[1]], iterator_to_array($part->walk(), false));
        self::assertCount(6, iterator_to_array($part->walk(true), false));
        self::assertSame($part->parts[1], $part->find('message/global'));
        self::assertNull($part->find('multipart/alternative'));
        self::assertSame($part, $part->find('multipart/mixed'));
    }

    public function testReadsTheMessagesOfADigest(): void
    {
        $part = new MimeParser()->parse("Content-Type: multipart/digest; boundary=d\n\n--d\n\nSubject: One\n\nfirst\n--d--");

        self::assertSame('message/rfc822', $part->parts[0]->type);
        self::assertSame('One', $part->parts[0]->message?->headers->get('Subject'));
    }

    public function testStopsAtTheDepth(): void
    {
        $nested = "Content-Type: message/rfc822\n\nContent-Type: message/rfc822\n\nContent-Type: message/rfc822\n\nSubject: deep\n\nx";
        $part = new MimeParser(new Limits(maxDepth: 3))->parse($nested);

        self::assertNotNull($part->message?->message);
        self::assertNull($part->message->message->message);
        self::assertSame("Subject: deep\n\nx", $part->message->message->body);

        $multipart = new MimeParser(new Limits(maxDepth: 1))->parse("Content-Type: multipart/mixed; boundary=b\n\n--b\n\nx\n--b--");
        self::assertSame([], $multipart->parts);
    }

    public function testStopsAtTheNumberOfParts(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\n\n".str_repeat("--b\n\nx\n", 10).'--b--';

        self::assertCount(3, new MimeParser(new Limits(maxParts: 4))->parse($raw)->parts);
        self::assertCount(10, new MimeParser()->parse($raw)->parts);
        self::assertNull(new MimeParser(new Limits(maxParts: 1))->parse("Content-Type: message/rfc822\n\nSubject: x\n\ny")->message);
        self::assertNotNull(new MimeParser(new Limits(maxParts: 2))->parse("Content-Type: message/rfc822\n\nSubject: x\n\ny")->message);
    }

    public function testReadsOnlyTheFirstOctets(): void
    {
        self::assertSame('0123', new MimeParser(new Limits(maxBytes: 10))->parse("A: 1\n\n0123456789")->body);
    }

    public function testBoundsTheHeaderFields(): void
    {
        self::assertCount(2, new MimeParser(new Limits(maxHeaders: 2))->parse("A: 1\nB: 2\nC: 3\n\nx")->headers->fields);
    }

    public function testRefusesALimitBelowOne(): void
    {
        foreach ([[0, 1, 1, 1, 1], [1, 0, 1, 1, 1], [1, 1, 0, 1, 1], [1, 1, 1, 0, 1], [1, 1, 1, 1, 0]] as $values) {
            try {
                new Limits(...$values);
                self::fail('The limits were accepted.');
            } catch (InvalidLimits $invalid) {
                self::assertSame('Every limit is at least 1.', $invalid->getMessage());
            }
        }

        $limits = new Limits(1, 1, 1, 1, 1);
        self::assertSame([1, 1, 1, 1, 1], [$limits->maxBytes, $limits->maxDepth, $limits->maxParts, $limits->maxHeaders, $limits->maxText]);
    }

    public function testKeepsAPartWithoutHeaders(): void
    {
        $part = new Part(new Headers(), 'text/plain', [], 'x');

        self::assertSame([$part], iterator_to_array($part->walk(true), false));
    }
}
