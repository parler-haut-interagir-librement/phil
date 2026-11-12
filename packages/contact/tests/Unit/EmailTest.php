<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\Email;
use Phil\Contact\Exception\InvalidContact;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Email::class)]
#[CoversClass(InvalidContact::class)]
final class EmailTest extends TestCase
{
    public function testAnAddressKeepsItsLocalPartAndLowersItsDomain(): void
    {
        $email = Email::of('  Ada.Love+chat@Example.COM ');

        self::assertSame('Ada.Love+chat@example.com', $email->value);
        self::assertSame('Ada.Love+chat@example.com', (string) $email);
        self::assertSame('mailto:Ada.Love+chat@example.com', $email->link());
        self::assertSame('ada@exämple.com', Email::of('ada@EXÄMPLE.com')->value);
        self::assertTrue($email->equals(Email::of('Ada.Love+chat@example.com')));
        self::assertFalse($email->equals(Email::of('ada.love+chat@example.com')));
    }

    public function testLoweringTheDomainKeepsTheWholeLocalPart(): void
    {
        self::assertSame("ada@i\u{307}nfo.com", Email::of('ada@İnfo.com')->value);
        self::assertSame('bob@key.com', Email::of("bob@\u{212A}ey.com")->value);
    }

    public function testTheLastAtSignSeparatesTheDomain(): void
    {
        self::assertSame('"a@b"@example.com', Email::of('"a@b"@Example.com')->value);
    }

    public function testTheLongestAddressIsTaken(): void
    {
        $longest = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.com';

        self::assertSame(254, \strlen($longest));
        self::assertSame($longest, Email::of($longest)->value);
    }

    public function testAnAddressInAnotherScriptIsMeasuredAsWritten(): void
    {
        $label = str_repeat('中', 20);
        $domain = implode('.', [$label, $label, $label, $label]).'.com';
        $longest = 'abcdef@'.$domain;

        self::assertSame(254, \strlen($longest));
        self::assertSame($longest, Email::of($longest)->value);

        $this->expectException(InvalidContact::class);

        Email::of('abcdefg@'.$domain);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notEmails(): iterable
    {
        yield 'no at sign' => ['ada.example.com'];
        yield 'no domain' => ['ada@'];
        yield 'no top-level domain' => ['ada@example'];
        yield 'a space' => ['ada love@example.com'];
        yield 'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 58).'.com'];
        yield 'a domain that is no name' => ['ada@-.com'];
        yield 'a domain IDNA refuses' => ['ada@'.str_repeat('é', 70).'.com'];
    }

    #[DataProvider('notEmails')]
    public function testWhatIsNoAddressIsRefused(string $input): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an email address.', mb_substr($input, 0, 100)));

        Email::of($input);
    }

    public function testALongInputIsCutInCharactersInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 100).'" is not an email address.');

        Email::of(str_repeat('é', 150));
    }
}
