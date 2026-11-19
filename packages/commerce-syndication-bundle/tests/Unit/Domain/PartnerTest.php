<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Domain;

use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Partner::class)]
final class PartnerTest extends TestCase
{
    public function testAPartnerIsKnownByItsCodeEndpointAndKey(): void
    {
        $key = Partners::supplierKey()->publicKey()->toString();
        $partner = Partner::of('supplier', 'The supplier', PartnerRole::Supplier, 'https://supplier.example/in', $key);

        self::assertSame(['supplier', 'The supplier', PartnerRole::Supplier, 'https://supplier.example/in', $key, [], 0, true], [
            $partner->code, $partner->name, $partner->role, $partner->endpoint(), $partner->publicKey(), $partner->skus(), $partner->share(), $partner->isActive(),
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function codes(): iterable
    {
        yield 'one character' => ['a'];
        yield 'sixty-three characters' => [str_repeat('a', 63)];
        yield 'digits and signs' => ['0shop_2.b-c'];
    }

    #[DataProvider('codes')]
    public function testACodeIsLowercaseAndShort(string $code): void
    {
        self::assertSame($code, Partners::supplier($code)->code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'sixty-four characters' => [str_repeat('a', 64)];
        yield 'uppercase' => ['Shop'];
        yield 'a sign first' => ['-shop'];
        yield 'a colon' => ['a:b'];
        yield 'a trailing newline' => ["shop\n"];
    }

    #[DataProvider('badCodes')]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a partner code: up to 63 lowercase letters, digits, "_", "." or "-".', $code));

        Partners::supplier($code);
    }

    public function testAPartnerHasAName(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The partner shop has a name.');

        Partner::of('shop', ' ', PartnerRole::Supplier, 'https://shop.example', Partners::supplierKey()->publicKey()->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badEndpoints(): iterable
    {
        yield 'plain http' => ['http://shop.example/in'];
        yield 'a scheme inside' => ['xhttps://shop.example/in'];
        yield 'no host' => ['https://'];
        yield 'a space' => ['https://shop.example/a b'];
        yield 'a trailing newline' => ["https://shop.example\n"];
        yield 'too long' => ['https://'.str_repeat('a', 2041)];
    }

    #[DataProvider('badEndpoints')]
    public function testAnEndpointIsHttps(string $endpoint): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an https endpoint.', $endpoint));

        Partner::of('shop', 'Shop', PartnerRole::Supplier, $endpoint, Partners::supplierKey()->publicKey()->toString());
    }

    public function testAnEndpointHoldsUpTo2048Characters(): void
    {
        $endpoint = 'https://'.str_repeat('a', 2040);

        self::assertSame($endpoint, Partner::of('shop', 'Shop', PartnerRole::Supplier, $endpoint, Partners::supplierKey()->publicKey()->toString())->endpoint());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        $key = Partners::supplierKey()->publicKey()->toString();

        yield 'a secret key' => [Partners::supplierKey()->toString()];
        yield 'no padding' => [substr($key, 0, -1)];
        yield 'another prefix' => ['whsk_'.substr($key, 5)];
        yield 'a trailing newline' => [$key."\n"];
        yield 'a leading character' => ['x'.$key];
    }

    #[DataProvider('badKeys')]
    public function testAKeyIsAnEd25519PublicKey(string $key): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A partner key is a "whpk_" Ed25519 public key.');

        Partner::of('shop', 'Shop', PartnerRole::Supplier, 'https://shop.example', $key);
    }

    public function testAPartnerMovesAndRotatesItsKey(): void
    {
        $partner = Partners::supplier();
        $key = Partners::resellerKey()->publicKey()->toString();

        $partner->reach('https://elsewhere.example/in', $key);

        self::assertSame(['https://elsewhere.example/in', $key], [$partner->endpoint(), $partner->publicKey()]);
    }

    public function testAMoveIsCheckedToo(): void
    {
        $partner = Partners::supplier();

        try {
            $partner->reach('http://elsewhere.example', $partner->publicKey());
            self::fail('A plain http endpoint was accepted.');
        } catch (InvalidValue) {
        }

        $this->expectException(InvalidValue::class);

        $partner->reach('https://elsewhere.example', 'whpk_');
    }

    public function testAResellerIsFedSkusAndKeepsAShare(): void
    {
        $reseller = Partners::reseller('reseller', ['TEA-1', 'TEA-1', 'MUG'], 999_999);

        self::assertSame([['TEA-1', 'MUG'], 999_999], [$reseller->skus(), $reseller->share()]);
        self::assertTrue($reseller->feeds('MUG'));
        self::assertFalse($reseller->feeds('CUP'));
    }

    public function testTheSkusAreCompared(): void
    {
        $reseller = Partners::reseller('reseller', ['01'], 0);

        self::assertSame(0, $reseller->share());
        self::assertFalse($reseller->feeds('1'));
    }

    public function testASuspendedResellerIsFedNothing(): void
    {
        $reseller = Partners::reseller();

        $reseller->suspend();
        self::assertFalse($reseller->isActive());
        self::assertFalse($reseller->feeds('TEA-1'));

        $reseller->resume();
        self::assertTrue($reseller->isActive());
        self::assertTrue($reseller->feeds('TEA-1'));
    }

    public function testASupplierIsNotFed(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Only a reseller is fed: supplier is a supplier.');

        Partners::supplier()->feed(['TEA-1'], 0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badSkus(): iterable
    {
        yield 'empty' => [''];
        yield 'a space' => ['TEA 1'];
        yield 'sixty-five characters' => [str_repeat('A', 65)];
        yield 'a trailing newline' => ["TEA\n"];
    }

    #[DataProvider('badSkus')]
    public function testASkuIsVisibleAscii(string $sku): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));

        Partners::reseller('reseller', ['TEA-1', $sku]);
    }

    public function testASkuHoldsUpTo64Characters(): void
    {
        self::assertSame([str_repeat('A', 64)], Partners::reseller('reseller', [str_repeat('A', 64)])->skus());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badShares(): iterable
    {
        yield 'negative' => [-1];
        yield 'everything' => [1_000_000];
    }

    #[DataProvider('badShares')]
    public function testAShareIsLessThanTheWholePrice(int $share): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A reseller keeps 0 to 999999 parts per million; %d is refused.', $share));

        Partners::reseller('reseller', ['TEA-1'], $share);
    }
}
