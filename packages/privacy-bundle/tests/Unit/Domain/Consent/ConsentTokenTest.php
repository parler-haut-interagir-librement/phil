<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Consent;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentToken::class)]
final class ConsentTokenTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAChoiceComesBackAsItWasWritten(): void
    {
        $token = new ConsentToken('secret');
        $read = $token->decode($token->encode(new ConsentChoice(self::ID, '2', ['analytics'], new DateTimeImmutable('2026-01-01T00:00:00Z'), true)));

        self::assertInstanceOf(ConsentChoice::class, $read);
        self::assertSame(self::ID, $read->id);
        self::assertSame('2', $read->version);
        self::assertSame(['analytics'], $read->granted);
        self::assertSame(1767225600, $read->decidedAtTimestamp());
        self::assertTrue($read->globalPrivacyControl);
    }

    public function testTheValueIsSafeInACookieAndCarriesNoPadding(): void
    {
        $value = new ConsentToken('secret')->encode(new ConsentChoice(self::ID, '1', ['analytics'], new DateTimeImmutable('2026-01-01T00:00:00Z')));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $value);
    }

    public function testAKeyThatDiffersReadsNothing(): void
    {
        $value = new ConsentToken('one')->encode(new ConsentChoice(self::ID, '1', [], new DateTimeImmutable('2026-01-01T00:00:00Z')));

        self::assertNull(new ConsentToken('two')->decode($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'no dot' => ['abc'];
        yield 'two dots' => ['a.b.c'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedValueReadsNothing(string $value): void
    {
        self::assertNull(new ConsentToken('secret')->decode($value));
    }

    public function testAValueTooLongIsNotEvenSigned(): void
    {
        $payload = str_repeat('a', ConsentToken::MAX_LENGTH);

        self::assertNull(new ConsentToken('secret')->decode($this->signed($payload)));
    }

    public function testAValueAtTheLimitIsRead(): void
    {
        $granted = array_map(static fn (int $i): string => 'c'.$i, range(1, 120));
        $value = new ConsentToken('secret')->encode(new ConsentChoice(self::ID, '1', $granted, new DateTimeImmutable('2026-01-01T00:00:00Z')));
        $padded = substr(json_encode(['i' => self::ID, 'v' => '1', 'g' => $granted, 't' => 1, 'p' => false, 'x' => ''], \JSON_THROW_ON_ERROR), 0, -2);
        $room = intdiv((ConsentToken::MAX_LENGTH - 44) * 3, 4) - \strlen($padded) - 2;
        $exact = $this->signed(rtrim(strtr(base64_encode($padded.str_repeat('x', $room).'"}'), '+/', '-_'), '='));

        self::assertInstanceOf(ConsentChoice::class, new ConsentToken('secret')->decode($value));
        self::assertLessThanOrEqual(ConsentToken::MAX_LENGTH, \strlen($exact));
        self::assertGreaterThan(ConsentToken::MAX_LENGTH - 4, \strlen($exact));
        self::assertInstanceOf(ConsentChoice::class, new ConsentToken('secret')->decode($exact));
    }

    /**
     * Signed with the right key, so that only the reading of the payload can refuse them.
     *
     * @return iterable<string, array{string}>
     */
    public static function shapes(): iterable
    {
        $id = self::ID;

        yield 'not base64' => ['***'];
        yield 'not JSON' => [self::encodeRaw('nope')];
        yield 'empty' => [''];
        yield 'too deep' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":[["a"]],"t":1,"p":false}')];
        yield 'a scalar' => [self::encodeRaw('5')];
        yield 'no id' => [self::encodeRaw('{"v":"1","g":[],"t":1,"p":false}')];
        yield 'no version' => [self::encodeRaw('{"i":"'.$id.'","g":[],"t":1,"p":false}')];
        yield 'no date' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":[],"p":false}')];
        yield 'date as text' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":[],"t":"1","p":false}')];
        yield 'no signal' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":[],"t":1}')];
        yield 'granted missing' => [self::encodeRaw('{"i":"'.$id.'","v":"1","t":1,"p":false}')];
        yield 'granted not a list' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":{"a":"b"},"t":1,"p":false}')];
        yield 'granted a scalar' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":"a","t":1,"p":false}')];
        yield 'granted not strings' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":[1],"t":1,"p":false}')];
        yield 'granted part strings' => [self::encodeRaw('{"i":"'.$id.'","v":"1","g":["a",1],"t":1,"p":false}')];
        yield 'bad id' => [self::encodeRaw('{"i":"x","v":"1","g":[],"t":1,"p":false}')];
    }

    public function testATokenSignedUnderTheKeyOfTheHmacDerivationReadsNothing(): void
    {
        $payload = self::encodeRaw('{"i":"'.self::ID.'","v":"2","g":["analytics"],"t":1767225600,"p":false}');
        $token = new ConsentToken('secret');

        self::assertInstanceOf(ConsentChoice::class, $token->decode($this->signed($payload)));
        self::assertNull($token->decode($this->signedWith($payload, hash_hmac('sha256', 'phil_privacy.consent', 'secret', true))));
    }

    #[DataProvider('shapes')]
    public function testAPayloadOfTheWrongShapeReadsNothing(string $payload): void
    {
        self::assertNull(new ConsentToken('secret')->decode($this->signed($payload)));
    }

    private static function encodeRaw(string $json): string
    {
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function signed(string $payload): string
    {
        return $this->signedWith($payload, hash_hkdf('sha256', 'secret', 32, 'phil_privacy.consent'));
    }

    private function signedWith(string $payload, string $key): string
    {
        return $payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');
    }
}
