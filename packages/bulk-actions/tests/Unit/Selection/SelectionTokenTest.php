<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Selection;

use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Selection\SelectionToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SelectionToken::class)]
#[CoversClass(SelectionUnavailable::class)]
final class SelectionTokenTest extends TestCase
{
    public function testATokenIsThirtyTwoRandomBytesInBase64Url(): void
    {
        $token = SelectionToken::generate();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token->value);
        self::assertSame(32, \strlen(sodium_base642bin($token->value, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)));
        self::assertNotSame($token->value, SelectionToken::generate()->value);
    }

    public function testItIsReadBackAndFiledUnderItsFingerprint(): void
    {
        $token = SelectionToken::generate();
        $read = SelectionToken::fromString($token->value);

        self::assertSame($token->value, $read->value);
        self::assertSame(hash('sha256', $token->value), $read->fingerprint());
        self::assertSame($token->fingerprint(), $read->fingerprint());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTokens(): iterable
    {
        yield 'empty' => [''];
        yield '42 characters' => [str_repeat('a', 42)];
        yield '44 characters' => [str_repeat('a', 44)];
        yield 'padding' => [str_repeat('a', 42).'='];
        yield 'a plus sign' => [str_repeat('a', 42).'+'];
        yield 'a final line feed' => [str_repeat('a', 43)."\n"];
        yield 'a leading space' => [' '.str_repeat('a', 42)];
    }

    #[DataProvider('notTokens')]
    public function testWhatCannotBeATokenIsUnavailable(string $value): void
    {
        $this->expectExceptionObject(SelectionUnavailable::create());

        SelectionToken::fromString($value);
    }

    public function testEveryBase64UrlCharacterIsAccepted(): void
    {
        $value = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmn-_0';

        self::assertSame($value, SelectionToken::fromString($value)->value);
        self::assertSame('opqrstuvwxyz0123456789-_ABCDEFGHIJKLMNOPQRS', SelectionToken::fromString('opqrstuvwxyz0123456789-_ABCDEFGHIJKLMNOPQRS')->value);
    }
}
