<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use Phil\IdentityProviderGoogleBundle\Application\Exception\TransmitterUnreachable;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventTokenReader;
use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventType;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\RiscHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SecurityEventTokenReader::class)]
#[CoversClass(TransmitterUnreachable::class)]
final class SecurityEventTokenReaderTest extends TestCase
{
    public function testAGoogleTokenIsRead(): void
    {
        $harness = new RiscHarness();
        $token = $harness->reader->read(RiscHarness::token('risc/event-type/sessions-revoked', claims: ['jti' => 'abc']));

        self::assertSame('abc', $token->id);
        self::assertSame(SecurityEventType::SessionsRevoked, $token->events[0]->type);
        self::assertSame(FakeGoogle::SUBJECT, $token->events[0]->subject);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'one part' => ['abc'];
        yield 'two parts' => ['a.b'];
        yield 'four parts' => ['a.b.c.d'];
        yield 'too long' => [str_repeat('a', 16_383).'.b.'];
    }

    #[DataProvider('notTokens')]
    public function testABodyThatIsNotATokenIsRefusedBeforeAnyRequest(string $body): void
    {
        $harness = new RiscHarness();

        try {
            $harness->reader->read($body);
            self::fail('The body was read.');
        } catch (InvalidSecurityEventToken $invalidSecurityEventToken) {
            self::assertSame('invalid_request', $invalidSecurityEventToken->error);
            self::assertSame('The body is not a security event token in the compact form.', $invalidSecurityEventToken->getMessage());
        }

        self::assertSame([], $harness->google->requests);
    }

    public function testATokenOfTheLongestLengthIsChecked(): void
    {
        $harness = new RiscHarness();

        $this->expectExceptionObject(InvalidSecurityEventToken::signature());
        $harness->reader->read('eyJhbGciOiJSUzI1NiJ9.'.str_repeat('a', 16_384 - 23).'.b');
    }

    public function testATokenSignedByAnotherKeyIsRefused(): void
    {
        $harness = new RiscHarness();

        $this->expectExceptionObject(InvalidSecurityEventToken::signature());
        $harness->reader->read(FakeGoogle::sign(['iss' => 'https://accounts.google.com/'], otherKey: true));
    }

    public function testKeysThatCannotBeReadMakeTheTransmitterRetry(): void
    {
        $harness = new RiscHarness();
        $harness->google->answers[FakeGoogle::JWKS] = new MockResponse('down', ['http_code' => 503]);

        try {
            $harness->reader->read(RiscHarness::token('risc/event-type/sessions-revoked'));
            self::fail('The token was read.');
        } catch (TransmitterUnreachable $transmitterUnreachable) {
            self::assertSame('The keys of the transmitter could not be read.', $transmitterUnreachable->getMessage());
            self::assertNotNull($transmitterUnreachable->getPrevious());
        }
    }

    public function testAnotherIssuerIsRefused(): void
    {
        $harness = new RiscHarness();

        $this->expectExceptionObject(InvalidSecurityEventToken::issuer());
        $harness->reader->read(RiscHarness::token('risc/event-type/sessions-revoked', claims: ['iss' => 'https://evil.example/']));
    }

    public function testAnotherAudienceIsRefused(): void
    {
        $harness = new RiscHarness();

        $this->expectExceptionObject(InvalidSecurityEventToken::audience());
        $harness->reader->read(RiscHarness::token('risc/event-type/sessions-revoked', claims: ['aud' => 'other.apps.googleusercontent.com']));
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function ages(): iterable
    {
        yield 'issued at the edge of the future' => [FakeGoogle::NOW + 300, true];
        yield 'issued too far in the future' => [FakeGoogle::NOW + 301, false];
        yield 'as old as accepted' => [FakeGoogle::NOW - 604_800, true];
        yield 'too old' => [FakeGoogle::NOW - 604_801, false];
    }

    #[DataProvider('ages')]
    public function testTheAgeOfATokenIsBounded(int $issuedAt, bool $accepted): void
    {
        $harness = new RiscHarness();
        $token = RiscHarness::token('risc/event-type/sessions-revoked', claims: ['iat' => $issuedAt]);

        if (!$accepted) {
            $this->expectExceptionObject(InvalidSecurityEventToken::malformed('The token was issued out of the time accepted.'));
        }

        self::assertSame($issuedAt, $harness->reader->read($token)->issuedAt);
    }
}
