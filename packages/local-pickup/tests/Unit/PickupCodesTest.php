<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\IssuedCode;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\ScannedCode;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\LocalPickup\Tests\Support\RandomIntSpy;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\SvgWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Before any call of the code under test: PHP remembers which `random_int()` a call site resolved to.
require_once __DIR__.'/../Support/random_int.php';

#[CoversClass(PickupCodes::class)]
#[CoversClass(IssuedCode::class)]
#[CoversClass(ScannedCode::class)]
final class PickupCodesTest extends TestCase
{
    public function testItIssuesAShortCodeAndKeepsOnlyItsKeyedDigest(): void
    {
        $codes = new PickupCodes(Fixture::SECRET);
        $issued = $codes->issue('WEB-1');

        self::assertSame('WEB-1', $issued->order);
        self::assertMatchesRegularExpression('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{8}$/D', $issued->code);
        self::assertSame(hash_hmac('sha256', "WEB-1\n".$issued->code, hash_hkdf('sha256', Fixture::SECRET, 32, 'phil_local_pickup.code_digest')), $issued->digest);
        self::assertTrue($codes->matches('WEB-1', $issued->code, $issued->digest));
        self::assertFalse($codes->matches('WEB-2', $issued->code, $issued->digest), 'The digest is bound to its order.');
        self::assertFalse(new PickupCodes(str_repeat('k', 32))->matches('WEB-1', $issued->code, $issued->digest), 'The digest is keyed.');
    }

    public function testCodesAreDrawnAtRandomOverTheWholeAlphabet(): void
    {
        $codes = new PickupCodes(Fixture::SECRET);
        $seen = [];

        for ($i = 0; $i < 200; ++$i) {
            $seen[] = $codes->issue('WEB-1')->code;
        }

        self::assertCount(200, array_unique($seen));
        $characters = count_chars(implode('', $seen), 3);
        self::assertSame('0123456789ABCDEFGHJKMNPQRSTVWXYZ', $characters);
    }

    public function testEachCharacterIsDrawnBetweenTheFirstAndTheLastOfTheAlphabet(): void
    {
        RandomIntSpy::$bounds = [];
        new PickupCodes(Fixture::SECRET)->issue('WEB-1');

        self::assertSame(array_fill(0, 8, [0, 31]), RandomIntSpy::$bounds);
    }

    public function testItReadsACodeAsPeopleTypeIt(): void
    {
        $codes = new PickupCodes(Fixture::SECRET);
        $digest = hash_hmac('sha256', "WEB-1\n7KQ2M91X", hash_hkdf('sha256', Fixture::SECRET, 32, 'phil_local_pickup.code_digest'));

        self::assertTrue($codes->matches('WEB-1', '7KQ2M91X', $digest));
        self::assertTrue($codes->matches('WEB-1', '7kq2-m9ix', $digest));
        self::assertTrue($codes->matches('WEB-1', ' 7KQ2 M9LX ', $digest));
        self::assertFalse($codes->matches('WEB-1', '7KQ2M91Y', $digest));
        self::assertFalse($codes->matches('WEB-1', '', $digest));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function typed(): iterable
    {
        yield 'plain' => ['7KQ2M91X', '7KQ2M91X'];
        yield 'grouped and lowercase' => ['7kq2-m91x', '7KQ2M91X'];
        yield 'O, I and L' => ['OIL0ilo1', '01101101'];
        yield 'seven characters' => ['7KQ2M91', null];
        yield 'nine characters' => ['7KQ2M91XA', null];
        yield 'U is not of the alphabet' => ['7KQ2M91U', null];
        yield 'a symbol' => ['7KQ2M91*', null];
        yield 'too much padding' => [str_repeat(' ', 25).'7KQ2M91X', null];
        yield 'padding within bounds' => [str_repeat(' ', 24).'7KQ2M91X', '7KQ2M91X'];
    }

    #[DataProvider('typed')]
    public function testItNormalizesACode(string $given, ?string $code): void
    {
        self::assertSame($code, PickupCodes::normalize($given));
    }

    public function testItRefusesAShortSecret(): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The pickup code secret is at least 32 bytes.');

        new PickupCodes(str_repeat('k', 31));
    }

    public function testItRefusesAnOrderOutOfShape(): void
    {
        $this->expectException(InvalidPickup::class);

        new PickupCodes(Fixture::SECRET)->issue('a:b');
    }

    public function testAnIssuedCodeIsShownInTwoGroupsAndAsAQrCode(): void
    {
        $issued = new IssuedCode('WEB-1', '7KQ2M91X', str_repeat('a', 64));

        self::assertSame('7KQ2-M91X', $issued->display());
        self::assertSame('WEB-1:7KQ2M91X', $issued->payload());
        self::assertSame('WEB-1:7KQ2M91X', $issued->qrCode()->data);
        self::assertSame(ErrorCorrection::Medium, $issued->qrCode()->level);
        self::assertSame(ErrorCorrection::High, $issued->qrCode(ErrorCorrection::High)->level);
        self::assertStringContainsString('<title>Pickup code</title>', $issued->svg());
        self::assertStringContainsString('<title>Code</title>', $issued->svg('Code', new SvgWriter()));
        self::assertSame(['order' => 'WEB-1', 'code' => '********'], $issued->__debugInfo());
        self::assertStringNotContainsString('7KQ2M91X', print_r($issued, true));
    }

    public function testAScannedPayloadGivesItsOrderAndCode(): void
    {
        $scanned = ScannedCode::parse('WEB-1:7kq2-m91x');

        self::assertSame('WEB-1', $scanned->order);
        self::assertSame('7KQ2M91X', $scanned->code);
        self::assertSame(['order' => 'WEB-1', 'code' => '********'], $scanned->__debugInfo());
    }

    public function testAScannedPayloadAtItsLongestIsRead(): void
    {
        $order = 'A'.str_repeat('b', 63);
        $payload = $order.':'.str_repeat(' ', 24).'7KQ2M91X';

        self::assertSame(ScannedCode::MAX_PAYLOAD, \strlen($payload));
        self::assertSame($order, ScannedCode::parse($payload)->order);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badPayloads(): iterable
    {
        yield 'no colon' => ['WEB-17KQ2M91X', 'is an order number, a colon and a pickup code'];
        yield 'too long' => ['A'.str_repeat('b', 63).':'.str_repeat(' ', 25).'7KQ2M91X', 'is an order number, a colon and a pickup code'];
        yield 'no code' => ['WEB-1:', 'ends with a pickup code'];
        yield 'wrong code' => ['WEB-1:7KQ2', 'ends with a pickup code'];
        yield 'no order' => [':7KQ2M91X', 'The order number is'];
        yield 'two colons' => ['WEB:1:7KQ2M91X', 'The order number is'];
    }

    #[DataProvider('badPayloads')]
    public function testItRefusesAPayloadOutOfShape(string $payload, string $message): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains($message);

        ScannedCode::parse($payload);
    }
}
