<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use Phil\ApiKeysBundle\Infrastructure\IpAnonymizer\AnonymizedClientAddress;
use Phil\ApiKeysBundle\Infrastructure\RateLimit\TokenKey;
use Phil\ApiKeysBundle\Infrastructure\Security\ApiTokenHandler;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TokenKey::class)]
#[CoversClass(AnonymizedClientAddress::class)]
final class IntegrationsTest extends TestCase
{
    public function testARequestCountsForItsToken(): void
    {
        $request = Request::create('/');
        self::assertNull(new TokenKey()->keyOf($request));

        $request->attributes->set(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, 'AAAAAAAAAAAA');
        self::assertSame('api_token:AAAAAAAAAAAA', new TokenKey()->keyOf($request));

        $request->attributes->set(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, 12);
        self::assertNull(new TokenKey()->keyOf($request));
    }

    public function testTheAddressIsAnonymizedByTheStrategy(): void
    {
        $addresses = new AnonymizedClientAddress(new Truncation());

        self::assertSame('203.0.113.0', $addresses->anonymize('203.0.113.45'));
        self::assertSame('2001:db8:1234::', $addresses->anonymize('2001:db8:1234:5678::1'));
        self::assertNull($addresses->anonymize('not an address'));
    }
}
