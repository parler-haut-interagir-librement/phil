<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\PayloadFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadFingerprint::class)]
final class PayloadFingerprintTest extends TestCase
{
    public function testARetryOfTheSameRequestMatches(): void
    {
        self::assertTrue(
            PayloadFingerprint::ofRequest('POST', '/api/things', '{"a":1}')
                ->equals(PayloadFingerprint::ofRequest('post', '/api/things', '{"a":1}')),
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function otherRequests(): iterable
    {
        yield 'another body' => ['POST', '/api/things', '{"a":2}'];
        yield 'the same body re-encoded' => ['POST', '/api/things', '{"a": 1}'];
        yield 'another target' => ['POST', '/api/other', '{"a":1}'];
        yield 'another method' => ['PUT', '/api/things', '{"a":1}'];
    }

    #[DataProvider('otherRequests')]
    public function testAnyOtherRequestDiffers(string $method, string $target, string $body): void
    {
        self::assertFalse(
            PayloadFingerprint::ofRequest('POST', '/api/things', '{"a":1}')
                ->equals(PayloadFingerprint::ofRequest($method, $target, $body)),
        );
    }

    /**
     * Without the length prefixes, moving bytes from the target to the body would keep the hash.
     */
    public function testMovingBytesAcrossPartsChangesTheFingerprint(): void
    {
        self::assertFalse(
            PayloadFingerprint::ofRequest('POST', '/a', 'bc')->equals(PayloadFingerprint::ofRequest('POST', '/ab', 'c')),
        );
    }

    /**
     * Pinned: a fingerprint is stored, so its material cannot change without every stored key turning into a
     * conflict.
     */
    public function testTheMaterialIsEachPartPrefixedWithItsLength(): void
    {
        self::assertSame(
            hash('sha256', '4:POST11:/api/things7:{"a":1}'),
            PayloadFingerprint::ofRequest('post', '/api/things', '{"a":1}')->hex,
        );
    }

    public function testAnOperationThatIsNotARequestHashesItsPartsInOrder(): void
    {
        self::assertSame(hash('sha256', '9:OrderPaid2:42'), PayloadFingerprint::ofParts('OrderPaid', '42')->hex);
        self::assertSame(hash('sha256', ''), PayloadFingerprint::ofParts()->hex);
    }

    public function testAStoredFingerprintReadsBackEqual(): void
    {
        $fingerprint = PayloadFingerprint::ofRequest('POST', '/api/things', '');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint->hex);
        self::assertTrue($fingerprint->equals(PayloadFingerprint::fromHex($fingerprint->hex)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badHex(): iterable
    {
        yield 'short' => [str_repeat('a', 63)];
        yield 'long' => [str_repeat('a', 65)];
        yield 'uppercase' => [str_repeat('A', 64)];
        yield 'not hexadecimal' => [str_repeat('g', 64)];
        yield 'trailing newline' => [str_repeat('a', 64)."\n"];
    }

    #[DataProvider('badHex')]
    public function testAMalformedStoredFingerprintIsRefused(string $hex): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        PayloadFingerprint::fromHex($hex);
    }
}
