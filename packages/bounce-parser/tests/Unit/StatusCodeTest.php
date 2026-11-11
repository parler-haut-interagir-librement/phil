<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit;

use Phil\BounceParser\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusCode::class)]
final class StatusCodeTest extends TestCase
{
    public function testReadsACode(): void
    {
        $code = StatusCode::parse(' 5.1.1 ');

        self::assertNotNull($code);
        self::assertSame([5, 1, 1], [$code->class, $code->subject, $code->detail]);
        self::assertSame('5.1.1', $code->toString());
        self::assertSame('5.1.1', (string) $code);
        self::assertTrue($code->isPermanent());
        self::assertFalse($code->isTransient());
        self::assertFalse($code->isSuccess());
        self::assertSame('addressing status', $code->describe());
        self::assertSame('4.123.999', StatusCode::parse('4.123.999')?->toString());
        self::assertSame(4, StatusCode::find('see 4.7.1')?->class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCodes(): iterable
    {
        yield 'class 3' => ['3.1.1'];
        yield 'long subject' => ['5.1234.1'];
        yield 'long detail' => ['5.1.1234'];
        yield 'text around' => ['x5.1.1'];
        yield 'text after' => ['5.1.1x'];
        yield 'empty' => [''];
    }

    #[DataProvider('notCodes')]
    public function testRefusesWhatIsNotACode(string $text): void
    {
        self::assertNull(StatusCode::parse($text));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function texts(): iterable
    {
        yield 'in a diagnostic' => ['smtp; 550 5.1.1 <a@example.com>: unknown', '5.1.1'];
        yield 'in parentheses' => ['no mailbox (#5.1.1)', '5.1.1'];
        yield 'end of a sentence' => ['Error 4.2.2.', '4.2.2'];
        yield 'version first' => ['Postfix 3.5.1.2 says 5.7.1 denied', '5.7.1'];
        yield 'address first' => ['host 192.5.2.1 said 4.4.1', '4.4.1'];
        yield 'after a digit' => ['15.1.1 then 2.0.0', '2.0.0'];
        yield 'nothing' => ['no code here 550', null];
    }

    #[DataProvider('texts')]
    public function testFindsTheFirstCodeOfAText(string $text, ?string $expected): void
    {
        self::assertSame($expected, StatusCode::find($text)?->toString());
    }

    public function testSaysWhichPermanentFailuresAreAboutTheMessage(): void
    {
        $about = static fn (string $code): bool => (bool) StatusCode::parse($code)?->isAboutTheMessage();

        self::assertTrue($about('5.2.2'));
        self::assertTrue($about('5.2.3'));
        self::assertTrue($about('5.3.4'));
        self::assertTrue($about('5.7.1'));
        self::assertTrue($about('5.7.26'));
        self::assertFalse($about('5.2.1'));
        self::assertFalse($about('5.2.4'));
        self::assertFalse($about('5.3.3'));
        self::assertFalse($about('5.3.5'));
        self::assertFalse($about('5.1.2'));
        self::assertFalse($about('5.4.2'));
        self::assertFalse($about('5.1.4'));
    }

    public function testDescribesEachSubject(): void
    {
        $subjects = array_map(static fn (int $subject): string => (string) StatusCode::parse('4.'.$subject.'.0')?->describe(), range(0, 8));

        self::assertSame([
            'other or undefined status',
            'addressing status',
            'mailbox status',
            'mail system status',
            'network and routing status',
            'mail delivery protocol status',
            'message content or media status',
            'security or policy status',
            'unknown status',
        ], $subjects);
        self::assertTrue(StatusCode::parse('4.0.0')?->isTransient());
        self::assertTrue(StatusCode::parse('2.0.0')?->isSuccess());
    }
}
