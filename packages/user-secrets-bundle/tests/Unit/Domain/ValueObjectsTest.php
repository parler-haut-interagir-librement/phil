<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Domain;

use LogicException;
use Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretValue;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Owner::class)]
#[CoversClass(SecretName::class)]
#[CoversClass(ServiceName::class)]
#[CoversClass(SecretValue::class)]
#[CoversClass(SealedSecret::class)]
#[CoversClass(SealedFormat::class)]
#[CoversClass(InvalidSecret::class)]
final class ValueObjectsTest extends TestCase
{
    public function testAnOwnerIsAnyOpaqueIdentifier(): void
    {
        self::assertSame('0192f3c4-7d1e-7a4b-9c2d-3e4f5a6b7c8d', Owner::fromString('0192f3c4-7d1e-7a4b-9c2d-3e4f5a6b7c8d')->value);
        self::assertSame('a', Owner::fromString('a')->value);
        self::assertSame(str_repeat('x', 255), Owner::fromString(str_repeat('x', 255))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOwners(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('x', 256)];
        yield 'space' => ['a b'];
        yield 'trailing newline' => ["member-1\n"];
        yield 'control' => ["a\x00"];
        yield 'delete' => ["a\x7F"];
    }

    #[DataProvider('badOwners')]
    public function testAnOwnerIsRefusedWhenNoStoreCanIndexIt(string $owner): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains('An owner is 1 to 255 bytes without whitespace or control characters.');

        Owner::fromString($owner);
    }

    public function testNamesAreShortLowercaseTokens(): void
    {
        self::assertSame('github.work-2_b', SecretName::fromString('github.work-2_b')->value);
        self::assertSame('o'.str_repeat('a', 63), SecretName::fromString('o'.str_repeat('a', 63))->value);
        self::assertSame('openai', ServiceName::fromString('openai')->value);
        self::assertSame('o'.str_repeat('a', 63), ServiceName::fromString('o'.str_repeat('a', 63))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['GitHub'];
        yield 'digit first' => ['1password'];
        yield 'too long' => ['o'.str_repeat('a', 64)];
        yield 'trailing newline' => ["github\n"];
        yield 'slash' => ['git/hub'];
    }

    #[DataProvider('badNames')]
    public function testASecretNameIsRefusedOutsideTheTokenShape(string $name): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a secret name: lowercase letters, digits, "_", "." and "-", starting with a letter, at most 64 characters.', $name));

        SecretName::fromString($name);
    }

    #[DataProvider('badNames')]
    public function testAServiceNameIsRefusedOutsideTheTokenShape(string $name): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a service name: lowercase letters, digits, "_", "." and "-", starting with a letter, at most 64 characters.', $name));

        ServiceName::fromString($name);
    }

    public function testASecretValueRevealsItselfOnlyWhenAsked(): void
    {
        $value = SecretValue::of('sk-live-123');

        self::assertSame('sk-live-123', $value->reveal());
        self::assertTrue($value->equals(SecretValue::of('sk-live-123')));
        self::assertFalse($value->equals(SecretValue::of('sk-live-124')));
        self::assertSame(['value' => '[redacted]'], $value->__debugInfo());
    }

    public function testASecretValueIsNeverEmpty(): void
    {
        $this->expectException(UserSecretsException::class);
        $this->expectExceptionMessageIsOrContains('A secret value cannot be empty.');

        SecretValue::of('');
    }

    public function testASecretValueIsNeverSerialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A secret value is never serialized.');

        SecretValue::of('sk-live-123')->__serialize();
    }

    public function testASecretValueIsNeverUnserialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A secret value is never unserialized.');

        SecretValue::of('sk-live-123')->__unserialize([]);
    }

    public function testASealedSecretNamesItsKey(): void
    {
        $sealed = new SealedSecret('k1', 'abc=');

        self::assertSame('k1', $sealed->keyId);
        self::assertSame('abc=', $sealed->ciphertext);
    }

    /**
     * @return iterable<string, array{string, ?SealedFormat}>
     */
    public static function heads(): iterable
    {
        yield 'the byte before it' => [base64_encode("\1nonce and box"), null];
        yield 'KeyManagement' => [base64_encode("\2envelope"), SealedFormat::KeyManagement];
        yield 'a lone byte' => [base64_encode("\2"), SealedFormat::KeyManagement];
        yield 'Algorithm' => [base64_encode("\3\1envelope"), SealedFormat::Algorithm];
        yield 'the byte after it' => [base64_encode("\4envelope"), null];
        yield 'unknown version' => [base64_encode("\x7Flater"), null];
        yield 'no version' => [base64_encode("\0"), null];
        yield 'empty' => ['', null];
        yield 'not base64' => ['%%%%', null];
        yield 'base64 without padding' => [rtrim(base64_encode("\1a"), '='), null];
    }

    #[DataProvider('heads')]
    public function testASealedSecretNamesItsFormatByItsFirstByte(string $ciphertext, ?SealedFormat $format): void
    {
        self::assertSame($format, new SealedSecret('k1', $ciphertext)->format());
    }

    public function testEachFormatHasItsByte(): void
    {
        self::assertSame([2, 3], array_map(static fn (SealedFormat $format): int => $format->value, SealedFormat::cases()));
        self::assertSame(SealedFormat::Algorithm, SealedFormat::current());
        self::assertSame([1], array_map(static fn (SealedAlgorithm $algorithm): int => $algorithm->value, SealedAlgorithm::cases()));
    }

    public function testASealedSecretGivesItsBytes(): void
    {
        self::assertSame("\3\1box", new SealedSecret('k1', base64_encode("\3\1box"))->bytes());
        self::assertSame('', new SealedSecret('k1', '')->bytes());
        self::assertNull(new SealedSecret('k1', '%%%%')->bytes());
    }
}
