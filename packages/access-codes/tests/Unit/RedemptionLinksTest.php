<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedemptionLinks::class)]
final class RedemptionLinksTest extends TestCase
{
    public function testTheLinkIsThePageWithTheCodeFilledIn(): void
    {
        $links = new RedemptionLinks(Url::fromString('https://shop.example/redeem'));

        self::assertSame('https://shop.example/redeem?code=7KQ2M9XDH4TBH', $links->for('7KQ2M9XDH4TBH')->toString());
    }

    public function testTheCodeFollowsTheQueryOfThePage(): void
    {
        $links = new RedemptionLinks(Url::fromString('https://shop.example:8443/en/redeem?from=card'), 'c');

        self::assertSame('https://shop.example:8443/en/redeem?from=card&c=7KQ2', $links->for('7KQ2')->toString());
    }

    public function testAPageOnLocalhostKeepsItsHost(): void
    {
        $links = new RedemptionLinks(Url::fromString('http://shop.localhost/redeem', allowLocalhost: true));

        self::assertSame('http://shop.localhost/redeem?code=AB', $links->for('AB')->toString());
    }

    public function testTheParameterIsANameOfTheQuery(): void
    {
        self::assertSame('a_1', new RedemptionLinks(Url::fromString('https://shop.example/'), 'a_1')->parameter);
        self::assertSame(str_repeat('a', 32), new RedemptionLinks(Url::fromString('https://shop.example/'), str_repeat('a', 32))->parameter);

        foreach (['', '1a', 'A', 'a-b', str_repeat('a', 33), "a\n"] as $parameter) {
            try {
                new RedemptionLinks(Url::fromString('https://shop.example/'), $parameter);
                self::fail(\sprintf('The parameter "%s" was accepted.', $parameter));
            } catch (InvalidConfiguration $exception) {
                self::assertStringStartsWith('The parameter of the code is 1 to 32 lowercase letters', $exception->getMessage());
            }
        }
    }

    public function testThePageHasNoFragment(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The address of the redemption page has no fragment.');

        new RedemptionLinks(Url::fromString('https://shop.example/redeem#form'));
    }

    public function testOnlyACanonicalCodeIsCarried(): void
    {
        $links = new RedemptionLinks(Url::fromString('https://shop.example/redeem'));

        self::assertSame('https://shop.example/redeem?code='.str_repeat('A', 65), $links->for(str_repeat('A', 65))->toString());

        foreach (['', '7KQ2-M9XD', 'abc', str_repeat('A', 66), "AB\n", 'A&b=c'] as $code) {
            try {
                $links->for($code);
                self::fail(\sprintf('The code "%s" was carried.', $code));
            } catch (InvalidConfiguration $exception) {
                self::assertSame('A redemption link carries a code in its canonical form.', $exception->getMessage());
            }
        }
    }

    public function testAnAddressTooLongIsRefused(): void
    {
        $links = new RedemptionLinks(Url::fromString('https://shop.example/'.str_repeat('a', 1990)));

        $this->expectException(InvalidConfiguration::class);

        $links->for(str_repeat('A', 60));
    }
}
