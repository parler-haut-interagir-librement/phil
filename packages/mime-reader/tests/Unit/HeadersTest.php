<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit;

use Phil\MimeReader\Headers;
use Phil\MimeReader\HeaderValue;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Mailbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
#[CoversClass(Mailbox::class)]
#[CoversClass(HeaderValue::class)]
#[CoversClass(EncodedWords::class)]
#[CoversClass(Charset::class)]
final class HeadersTest extends TestCase
{
    public function testFoldedFieldsAreUnfoldedAndTrimmed(): void
    {
        $headers = Headers::parse("Subject:  one\r\n two\r\n\tthree \r\nX-Empty:\r\nX-Last: end");

        self::assertSame([['Subject', "one two\tthree"], ['X-Empty', ''], ['X-Last', 'end']], $headers->fields);
    }

    public function testLinesWithoutAValidNameAreSkipped(): void
    {
        $headers = Headers::parse(" orphan continuation\nno colon here\nBad Name: x\n: empty name\nGood: y\n continued\nÉ: z\nA:b:c");

        self::assertSame([['Good', 'y continued'], ['A', 'b:c']], $headers->fields);
    }

    public function testAFieldFollowedByAnInvalidLineEndsThere(): void
    {
        self::assertSame([['A', '1']], Headers::parse("A: 1\nnot a field\n continued")->fields);
        self::assertSame([['A', '1']], Headers::parse("A: 1\n\n more")->fields);
    }

    public function testReadingStopsAtTheLimit(): void
    {
        $headers = Headers::parse("A: 1\nB: 2\nC: 3", 2);

        self::assertSame([['A', '1'], ['B', '2']], $headers->fields);
        self::assertSame([['A', '1 more']], Headers::parse("A: 1\n more\nB: 2", 1)->fields);
    }

    public function testNamesAreComparedWithoutCase(): void
    {
        $headers = new Headers([['Received', 'one'], ['X-A', 'a'], ['received', 'two']]);

        self::assertSame('one', $headers->get('RECEIVED'));
        self::assertSame(['one', 'two'], $headers->all('Received'));
        self::assertSame([], $headers->all('Missing'));
        self::assertNull($headers->get('Missing'));
        self::assertTrue($headers->has('x-a'));
        self::assertFalse($headers->has('x-b'));
        self::assertSame([], new Headers()->fields);
    }

    public function testDecodedValuesAreUtf8(): void
    {
        $headers = new Headers([
            ['Subject', '=?UTF-8?B?w6l0w6k=?= =?UTF-8?Q?_caf=C3=A9?= ok'],
            ['X-Raw', "caf\xE9"],
        ]);

        self::assertSame('été café ok', $headers->decoded('Subject'));
        self::assertSame('caf?', $headers->decoded('X-Raw'));
        self::assertNull($headers->decoded('Missing'));
    }

    public function testAParameterizedFieldIsRead(): void
    {
        $headers = new Headers([['Content-Type', 'text/plain; charset=utf-8; format=flowed']]);

        self::assertEquals(new HeaderValue('text/plain', ['charset' => 'utf-8', 'format' => 'flowed']), $headers->parameterized('content-type'));
        self::assertEquals(new HeaderValue('text/plain', ['charset' => 'utf-8']), $headers->parameterized('Content-Type', 1));
        self::assertNull($headers->parameterized('Content-Disposition'));
    }

    public function testMailboxesAreReadFromEveryFieldOfTheName(): void
    {
        $headers = new Headers([
            ['To', '"Doe, J" <j@x.org>, (comment, with comma) a@b.c, Team: =?utf-8?q?R=C3=A9mi?= <r@y.z>;, <>'],
            ['To', 'plain@example.com (Plain (nested) name)'],
            ['Cc', 'undisclosed-recipients:;'],
            ['Bcc', '"Quote \"in\" name" <q@x.org>, not-an-address, "a\\\\b" <b@x.org>'],
            ['Reply-To', 'Broken <open@x.org'],
        ]);

        self::assertEquals([
            new Mailbox('j@x.org', 'Doe, J'),
            new Mailbox('a@b.c'),
            new Mailbox('r@y.z', 'Rémi'),
            new Mailbox('plain@example.com'),
        ], $headers->mailboxes('to'));
        self::assertSame([], $headers->mailboxes('Cc'));
        self::assertEquals([new Mailbox('q@x.org', 'Quote "in" name'), new Mailbox('b@x.org', 'a\\b')], $headers->mailboxes('Bcc'));
        self::assertEquals([new Mailbox('open@x.org', 'Broken')], $headers->mailboxes('Reply-To'));
        self::assertEquals([new Mailbox('j@x.org', 'Doe, J'), new Mailbox('a@b.c')], $headers->mailboxes('To', 2));
        self::assertSame([], $headers->mailboxes('To', 0));
    }

    public function testAnAngleAddressKeepsItsCommas(): void
    {
        $headers = new Headers([['From', 'Name <"odd,local"@x.org>, other@y.org']]);

        self::assertEquals([new Mailbox('"odd,local"@x.org', 'Name'), new Mailbox('other@y.org')], $headers->mailboxes('From'));
    }

    public function testAnEscapeInAQuotedNameOrCommentIsKeptInside(): void
    {
        $headers = new Headers([['From', '"a\\", b" <a@x.org>, (c \\) d, e@x.org) f@x.org']]);

        self::assertEquals([new Mailbox('a@x.org', 'a", b'), new Mailbox('f@x.org')], $headers->mailboxes('From'));
    }

    public function testTheDomainOfAMailbox(): void
    {
        self::assertSame('example.org', new Mailbox('a@EXAMPLE.org')->domain());
        self::assertSame('c', new Mailbox('"a@b"@c')->domain());
        self::assertNull(new Mailbox('a@')->domain());
        self::assertNull(new Mailbox('local')->domain());
        self::assertSame('b', new Mailbox('@b')->domain());
        self::assertNull(new Mailbox('x')->name);
    }
}
