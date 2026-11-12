<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\MissingIdempotencyKey;
use Phil\Idempotency\IdempotencyKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyKey::class)]
#[CoversClass(MissingIdempotencyKey::class)]
final class IdempotencyKeyTest extends TestCase
{
    public function testAGeneratedKeyIsARandomUuidV4(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            IdempotencyKey::generate()->value,
        );
    }

    public function testTwoIntentionsNeverShareAKey(): void
    {
        self::assertFalse(IdempotencyKey::generate()->equals(IdempotencyKey::generate()));
    }

    /**
     * The client keeps the key between retries; reading it back gives the same key.
     */
    public function testAKeptKeyIsTheSameKey(): void
    {
        $key = IdempotencyKey::generate();

        self::assertTrue($key->equals(IdempotencyKey::fromString($key->value)));
    }

    public function testTheWriterSendsAStructuredFieldString(): void
    {
        self::assertSame('"abc-123"', IdempotencyKey::fromString('abc-123')->toHeaderValue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function headerForms(): iterable
    {
        yield 'quoted' => ['"8e03978e-40d5-43e8-bc93-6894a57f9324"'];
        yield 'bare' => ['8e03978e-40d5-43e8-bc93-6894a57f9324'];
        yield 'surrounded by spaces' => ['  "8e03978e-40d5-43e8-bc93-6894a57f9324" '];
    }

    #[DataProvider('headerForms')]
    public function testTheReaderAcceptsTheQuotedAndTheBareForm(string $header): void
    {
        self::assertSame('8e03978e-40d5-43e8-bc93-6894a57f9324', IdempotencyKey::fromHeader($header)->value);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function missing(): iterable
    {
        yield 'absent' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    #[DataProvider('missing')]
    public function testAMissingHeaderIsTheContractsBadRequest(?string $header): void
    {
        $this->expectException(MissingIdempotencyKey::class);

        IdempotencyKey::fromHeader($header);
    }

    public function testAnEmptyQuotedStringIsNoKey(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);
        $this->expectExceptionMessageIsOrContains('An idempotency key is not empty.');

        IdempotencyKey::fromHeader('""');
    }

    public function testAnEmptyKeyIsRefusedAsEmpty(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);
        $this->expectExceptionMessageIsOrContains('An idempotency key is not empty.');

        IdempotencyKey::fromString('');
    }

    /**
     * Only a value both opened and closed by a quote is a quoted string; a lone quote is a malformed key.
     *
     * @return iterable<string, array{string}>
     */
    public static function halfQuoted(): iterable
    {
        yield 'a lone quote' => ['"'];
        yield 'a closing quote only' => ['abc"'];
        yield 'an opening quote only' => ['"abc'];
    }

    #[DataProvider('halfQuoted')]
    public function testAHalfQuotedHeaderIsRefused(string $header): void
    {
        $this->expectException(InvalidIdempotencyValue::class);
        $this->expectExceptionMessageIsOrContains('printable ASCII only');

        IdempotencyKey::fromHeader($header);
    }

    public function testTheLongestKeyIsAccepted(): void
    {
        self::assertSame(255, \strlen(IdempotencyKey::fromString(str_repeat('a', 255))->value));
    }

    public function testALongerKeyIsRefused(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);
        $this->expectExceptionMessageIsOrContains('at most 255 characters long; this one is 256.');

        IdempotencyKey::fromString(str_repeat('a', 256));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'quote inside' => ['a"b'];
        yield 'backslash' => ['a\\b'];
        yield 'control character' => ["a\nb"];
        yield 'non-ASCII' => ['clé'];
        yield 'leading space' => [' abc'];
        yield 'trailing space' => ['abc '];
    }

    #[DataProvider('badKeys')]
    public function testAKeyHoldsPrintableAsciiOnly(string $value): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        IdempotencyKey::fromString($value);
    }

    public function testAnInnerSpaceIsAllowed(): void
    {
        self::assertSame('a b', IdempotencyKey::fromString('a b')->value);
    }
}
