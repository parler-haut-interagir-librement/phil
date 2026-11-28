<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Domain;

use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEvent;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityEventToken::class)]
#[CoversClass(SecurityEvent::class)]
#[CoversClass(InvalidSecurityEventToken::class)]
final class SecurityEventTokenTest extends TestCase
{
    private const string DISABLED = 'https://schemas.openid.net/secevent/risc/event-type/account-disabled';

    /**
     * A token as Google's Cross-Account Protection sends it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function google(array $overrides = []): array
    {
        return array_replace([
            'iss' => 'https://accounts.google.com/',
            'aud' => '1234-test.apps.googleusercontent.com',
            'iat' => 1_795_176_000,
            'jti' => '756E69717565206964656E746966696572',
            'events' => [
                self::DISABLED => [
                    'subject' => ['subject_type' => 'iss-sub', 'iss' => 'https://accounts.google.com/', 'sub' => '7375626A656374'],
                    'reason' => 'hijacking',
                ],
            ],
        ], $overrides);
    }

    public function testAGoogleTokenIsRead(): void
    {
        $token = SecurityEventToken::fromClaims(self::google());

        self::assertSame('https://accounts.google.com/', $token->issuer);
        self::assertSame('756E69717565206964656E746966696572', $token->id);
        self::assertSame(1_795_176_000, $token->issuedAt);
        self::assertSame(['1234-test.apps.googleusercontent.com'], $token->audiences);
        self::assertEquals([new SecurityEvent(SecurityEventType::AccountDisabled, 'https://accounts.google.com/', '7375626A656374', 'hijacking')], $token->events);
    }

    public function testAVerificationEventCarriesItsStateAndNoSubject(): void
    {
        $token = SecurityEventToken::fromClaims(self::google(['events' => ['https://schemas.openid.net/secevent/risc/event-type/verification' => ['state' => 'a state']]]));

        self::assertEquals([new SecurityEvent(SecurityEventType::Verification, state: 'a state')], $token->events);
    }

    public function testASubjectOfTheSharedSignalsFrameworkIsRead(): void
    {
        $token = SecurityEventToken::fromClaims(self::google([
            'aud' => ['a', 'b'],
            'events' => ['https://schemas.openid.net/secevent/caep/event-type/session-revoked' => ['subject' => ['format' => 'iss_sub', 'iss' => 'https://idp.example', 'sub' => 'abc']]],
        ]));

        self::assertSame(['a', 'b'], $token->audiences);
        self::assertEquals([new SecurityEvent(SecurityEventType::SessionRevoked, 'https://idp.example', 'abc')], $token->events);
    }

    public function testTheSubjectOfTheWholeTokenStandsForAnEventWithout(): void
    {
        $token = SecurityEventToken::fromClaims(self::google([
            'sub_id' => ['format' => 'iss_sub', 'iss' => 'https://idp.example', 'sub' => 'abc'],
            'events' => ['https://schemas.openid.net/secevent/risc/event-type/sessions-revoked' => []],
        ]));

        self::assertEquals([new SecurityEvent(SecurityEventType::SessionsRevoked, 'https://idp.example', 'abc')], $token->events);
    }

    public function testASubjectOfAnotherFormatIsNoSubject(): void
    {
        $token = SecurityEventToken::fromClaims(self::google([
            'sub_id' => 'not an object',
            'events' => ['https://schemas.openid.net/secevent/oauth/event-type/token-revoked' => ['subject' => ['subject_type' => 'oauth_token', 'token_type' => 'refresh_token', 'token' => 'x']]],
        ]));

        self::assertEquals([new SecurityEvent(SecurityEventType::TokenRevoked)], $token->events);
    }

    public function testEventsOfAnUnknownTypeAreLeftOut(): void
    {
        $token = SecurityEventToken::fromClaims(self::google(['events' => [
            'https://example.com/unknown' => [],
            'https://schemas.openid.net/secevent/risc/event-type/account-enabled' => ['subject' => ['subject_type' => 'iss-sub', 'iss' => 'i', 'sub' => 's']],
        ]]));

        self::assertEquals([new SecurityEvent(SecurityEventType::AccountEnabled, 'i', 's')], $token->events);
    }

    public function testTheSubjectTypeIsReadBeforeTheFormat(): void
    {
        $subject = ['subject_type' => 'unknown', 'format' => 'iss-sub', 'iss' => 'i', 'sub' => 's'];
        $token = SecurityEventToken::fromClaims(self::google(['events' => [self::DISABLED => ['subject' => $subject]]]));

        self::assertEquals([new SecurityEvent(SecurityEventType::AccountDisabled)], $token->events);
    }

    public function testSixteenEventsAndAudiencesAreRead(): void
    {
        $events = [];

        for ($i = 0; $i < 16; ++$i) {
            $events['https://example.com/'.$i] = [];
        }

        $token = SecurityEventToken::fromClaims(self::google(['events' => $events, 'aud' => array_fill(0, 16, 'a')]));

        self::assertSame([], $token->events);
        self::assertCount(16, $token->audiences);
    }

    public function testTheLongestIdentifiersAreRead(): void
    {
        $token = SecurityEventToken::fromClaims(self::google([
            'iss' => str_repeat('i', 2048),
            'jti' => str_repeat('j', 255),
            'iat' => 0,
            'aud' => [str_repeat('a', 2048)],
            'events' => [self::DISABLED => ['subject' => ['subject_type' => 'iss-sub', 'iss' => str_repeat('i', 2048), 'sub' => str_repeat('s', 255)], 'reason' => str_repeat('r', 255)]],
        ]));

        self::assertSame(255, \strlen($token->id));
        self::assertSame(2048, \strlen($token->audiences[0]));
        self::assertSame(0, $token->issuedAt);
        self::assertSame(255, \strlen((string) $token->events[0]->subject));
        self::assertSame(255, \strlen((string) $token->events[0]->reason));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformed(): iterable
    {
        $subject = static fn (array $subject): array => ['events' => [self::DISABLED => ['subject' => $subject]]];
        $tooMany = [];

        for ($i = 0; $i < 17; ++$i) {
            $tooMany['https://example.com/'.$i] = [];
        }

        yield 'no issuer' => [['iss' => null], 'The token has no "iss".'];
        yield 'an empty issuer' => [['iss' => ''], 'The claim "iss" is not a string of 1 to 2048 bytes.'];
        yield 'an issuer too long' => [['iss' => str_repeat('i', 2049)], 'The claim "iss" is not a string of 1 to 2048 bytes.'];
        yield 'no identifier' => [['jti' => null], 'The token has no "jti".'];
        yield 'an empty identifier' => [['jti' => ''], 'The claim "jti" is not a string of 1 to 255 bytes.'];
        yield 'an identifier too long' => [['jti' => str_repeat('j', 256)], 'The claim "jti" is not a string of 1 to 255 bytes.'];
        yield 'an issue time as a string' => [['iat' => '1795176000'], 'The token has no "iat" of whole seconds.'];
        yield 'an issue time before 1970' => [['iat' => -1], 'The token has no "iat" of whole seconds.'];
        yield 'no events' => [['events' => null], 'The token has no "events" object.'];
        yield 'events as a list' => [['events' => [[]]], 'The token has no "events" object.'];
        yield 'empty events' => [['events' => []], 'The token has no "events" object.'];
        yield 'too many events' => [['events' => $tooMany], 'The token carries more than 16 events.'];
        yield 'an event that is not an object' => [['events' => [self::DISABLED => 'x']], 'An event of the token is not an object.'];
        yield 'no audience' => [['aud' => null], 'The token has no "aud".'];
        yield 'no audience in a list' => [['aud' => []], 'The token has no "aud".'];
        yield 'too many audiences' => [['aud' => array_fill(0, 17, 'a')], 'The token has no "aud".'];
        yield 'an audience that is a number' => [['aud' => [1]], 'An audience of the token is not a string.'];
        yield 'an empty audience' => [['aud' => ['']], 'An audience of the token is not a string.'];
        yield 'an audience too long' => [['aud' => [str_repeat('a', 2049)]], 'An audience of the token is not a string.'];
        yield 'a subject without an issuer' => [$subject(['subject_type' => 'iss-sub', 'sub' => 's']), 'The subject of an event has no "iss".'];
        yield 'a subject without an identifier' => [$subject(['subject_type' => 'iss-sub', 'iss' => 'i']), 'The subject of an event has no "sub".'];
        yield 'a subject identifier too long' => [$subject(['subject_type' => 'iss-sub', 'iss' => 'i', 'sub' => str_repeat('s', 256)]), 'The claim "sub" is not a string of 1 to 255 bytes.'];
        yield 'a reason that is not a string' => [['events' => [self::DISABLED => ['reason' => 1]]], 'The claim "reason" is not a string of 1 to 255 bytes.'];
        yield 'a state too long' => [['events' => [self::DISABLED => ['state' => str_repeat('s', 256)]]], 'The claim "state" is not a string of 1 to 255 bytes.'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('malformed')]
    public function testAMalformedTokenIsRefused(array $overrides, string $message): void
    {
        $claims = self::google($overrides);

        foreach ($overrides as $name => $value) {
            if (null === $value) {
                unset($claims[$name]);
            }
        }

        try {
            SecurityEventToken::fromClaims($claims);
            self::fail('The token was read.');
        } catch (InvalidSecurityEventToken $invalidSecurityEventToken) {
            self::assertSame('invalid_request', $invalidSecurityEventToken->error);
            self::assertSame($message, $invalidSecurityEventToken->getMessage());
        }
    }

    public function testTheErrorsCarryTheirCode(): void
    {
        self::assertSame('invalid_key', InvalidSecurityEventToken::key('k')->error);
        self::assertSame('k', InvalidSecurityEventToken::key('k')->getMessage());
        self::assertSame('invalid_issuer', InvalidSecurityEventToken::issuer()->error);
        self::assertSame('The token was not issued by the transmitter.', InvalidSecurityEventToken::issuer()->getMessage());
        self::assertSame('invalid_audience', InvalidSecurityEventToken::audience()->error);
        self::assertSame('The token is not meant for this receiver.', InvalidSecurityEventToken::audience()->getMessage());
        self::assertSame('authentication_failed', InvalidSecurityEventToken::signature()->error);
        self::assertSame('The signature of the token does not hold.', InvalidSecurityEventToken::signature()->getMessage());
    }
}
