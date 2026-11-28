<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application\Audit;

use Phil\UserSecretsBundle\Application\Audit\FailureReason;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The message of a failure, kept for a log without the secrets a connector's diagnostic may carry.
 */
#[CoversClass(FailureReason::class)]
final class FailureReasonTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function diagnostics(): iterable
    {
        yield 'a plain message' => ['The revocation endpoint answered 503.', 'The revocation endpoint answered 503.'];
        yield 'a URL query and fragment' => ['GET https://api.example.com/v1/revoke?token=abc&x=1#frag failed', 'GET https://api.example.com/v1/revoke?[redacted] failed'];
        yield 'a URL fragment alone' => ['See https://example.com/cb#access_token=abc now', 'See https://example.com/cb?[redacted] now'];
        yield 'the user information of a URL' => ['Cannot reach https://client:s3cret@auth.example.com/revoke', 'Cannot reach https://auth.example.com/revoke'];
        yield 'a bearer token' => ['Header was Bearer abc.def', 'Header was Bearer [redacted]'];
        yield 'a basic credential' => ['sent basic dXNlcjpwYXNz, refused', 'sent basic [redacted], refused'];
        yield 'an access token field' => ['{"access_token":"abc","expires_in":3600}', '{"access_token":"[redacted]","expires_in":3600}'];
        yield 'a client secret field' => ['client_secret = hunter2; retry', 'client_secret = [redacted]; retry'];
        yield 'an API key field' => ['api-key: k-1 rejected', 'api-key: [redacted] rejected'];
        yield 'a password field' => ["password='p'", "password='[redacted]'"];
        yield 'a code verifier field' => ['code_verifier=abc', 'code_verifier=[redacted]'];
        yield 'a status code stays' => ['status code: 401', 'status code: 401'];
        yield 'a long opaque value' => ['token gho_16C7e42F292c6912E7710c838347Ae178B4a refused', 'token [redacted] refused'];
        yield 'a JSON web token' => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV', '[redacted].[redacted].[redacted]'];
        yield 'a long word without digit stays' => ['Symfony\Component\HttpClient\Exception\ClientExceptionInterface', 'Symfony\Component\HttpClient\Exception\ClientExceptionInterface'];
        yield 'a long number without letter stays' => ['order 123456789012345678901234', 'order 123456789012345678901234'];
        yield 'a 19-character value stays' => ['id abcdefghij123456789', 'id abcdefghij123456789'];
        yield 'a 20-character value goes' => ['id abcdefghij1234567890', 'id [redacted]'];
        yield 'line breaks and control characters' => ["first\r\nsecond\tthird\0", 'first second third'];
    }

    #[DataProvider('diagnostics')]
    public function testItRedactsWhatMayBeASecret(string $message, string $reason): void
    {
        self::assertSame($reason, FailureReason::of(new RuntimeException($message)));
    }

    public function testItRemovesTheKnownValuesWhateverTheirShape(): void
    {
        $reason = FailureReason::of(
            new RuntimeException('Refused tok.en/1 and r-e-f-r-e-s-h, not at.'),
            SecretValue::of('tok.en/1'),
            null,
            SecretValue::of('r-e-f-r-e-s-h'),
            SecretValue::of('at'),
        );

        self::assertSame('Refused [redacted] and [redacted], not at.', $reason);
    }

    public function testItKeepsTheReasonShort(): void
    {
        $reason = FailureReason::of(new RuntimeException(str_repeat('word ', 300)));

        self::assertSame(FailureReason::MAX_LENGTH, mb_strlen($reason));
        self::assertStringEndsWith('…', $reason);
        self::assertSame(rtrim(str_repeat('word ', 48)), rtrim(mb_substr($reason, 0, 239)));
    }

    public function testAReasonOfTheMaximumLengthIsKeptWhole(): void
    {
        $message = str_repeat('a', FailureReason::MAX_LENGTH);

        self::assertSame($message, FailureReason::of(new RuntimeException($message)));
    }

    public function testTheLengthIsCountedInCharactersNotInBytes(): void
    {
        $short = str_repeat('é', FailureReason::MAX_LENGTH);
        $long = str_repeat('é', FailureReason::MAX_LENGTH + 60);

        self::assertSame($short, FailureReason::of(new RuntimeException($short)));
        self::assertSame(str_repeat('é', FailureReason::MAX_LENGTH - 1).'…', FailureReason::of(new RuntimeException($long)));
    }

    public function testItReadsOnlyTheStartOfALongMessage(): void
    {
        $reason = FailureReason::of(new RuntimeException(str_repeat(' ', FailureReason::MAX_INPUT).'secret-tail'));

        self::assertSame('', $reason);
    }

    public function testAMessageCutInsideACharacterStaysReadable(): void
    {
        $reason = FailureReason::of(new RuntimeException(str_repeat('a', FailureReason::MAX_INPUT - 1).'é'));

        self::assertTrue(mb_check_encoding($reason, 'UTF-8'));
        self::assertStringStartsWith('aaa', $reason);
    }
}
