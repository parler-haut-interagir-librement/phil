<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\IpAnonymizer\Truncation;
use Phil\RateLimitBundle\Infrastructure\IpAnonymizer\NetworkAddress;
use Phil\RateLimitBundle\Infrastructure\Key\ClientIpKey;
use Phil\RateLimitBundle\Infrastructure\Key\IpRouteKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserOrIpKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(ClientIpKey::class)]
#[CoversClass(UserKey::class)]
#[CoversClass(UserOrIpKey::class)]
#[CoversClass(IpRouteKey::class)]
#[CoversClass(NetworkAddress::class)]
final class KeysTest extends TestCase
{
    public function testTheAddressIsKeptAsAKeyedHash(): void
    {
        $key = new ClientIpKey('secret');

        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.7', 'secret'), $key->keyOf($this->request('203.0.113.7')));
        self::assertNotSame($key->keyOf($this->request('203.0.113.7')), $key->keyOf($this->request('203.0.113.8')));
        self::assertNull($key->keyOf($this->request(null)));
    }

    public function testAnIpv6AddressCountsByItsSubscriberNetworkWithoutNormalizer(): void
    {
        $key = new ClientIpKey('secret');

        self::assertSame('ip:'.hash_hmac('sha256', '2001:db8:1:2::', 'secret'), $key->keyOf($this->request('2001:db8:1:2:aaaa::1')));
        self::assertSame($key->keyOf($this->request('2001:db8:1:2:aaaa::1')), $key->keyOf($this->request('2001:db8:1:2:bbbb::9')));
        self::assertNotSame($key->keyOf($this->request('2001:db8:1:2::1')), $key->keyOf($this->request('2001:db8:1:3::1')));
    }

    public function testTheNetworkSharesOneKey(): void
    {
        $key = new ClientIpKey('secret', new NetworkAddress(new Truncation(24, 64)));

        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.0', 'secret'), $key->keyOf($this->request('203.0.113.7')));
        self::assertSame($key->keyOf($this->request('2001:db8:1:2:aaaa::1')), $key->keyOf($this->request('2001:db8:1:2:bbbb::9')));
        self::assertNotSame($key->keyOf($this->request('2001:db8:1:2::1')), $key->keyOf($this->request('2001:db8:1:3::1')));
        self::assertSame('not an address', new NetworkAddress(new Truncation())->normalize('not an address'));
    }

    public function testTheUserIsKeptAsAKeyedHash(): void
    {
        $tokens = new TokenStorage();
        $key = new UserKey($tokens, 'secret');

        self::assertNull($key->keyOf(Request::create('/')));
        self::assertNull(new UserKey(null, 'secret')->keyOf(Request::create('/')));

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.org', null), 'main'));

        self::assertSame('user:'.hash_hmac('sha256', 'ada@example.org', 'secret'), $key->keyOf(Request::create('/')));
    }

    public function testTheUserElseTheAddress(): void
    {
        $tokens = new TokenStorage();
        $ip = new ClientIpKey('secret');
        $user = new UserKey($tokens, 'secret');
        $key = new UserOrIpKey($user, $ip);
        $request = $this->request('203.0.113.7');

        self::assertSame($ip->keyOf($request), $key->keyOf($request));

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));

        self::assertSame($user->keyOf($request), $key->keyOf($request));
    }

    public function testTheAddressAndTheRouteTogether(): void
    {
        $ip = new ClientIpKey('secret');
        $key = new IpRouteKey($ip, 'pepper');
        $search = $this->request('203.0.113.7', '/search?q=1');
        $search->attributes->set('_route', 'search');

        $login = $this->request('203.0.113.7', '/login');
        $login->attributes->set('_route', 'login');

        $unrouted = $this->request('203.0.113.7', '/static/page');

        self::assertSame('ip_route:'.hash_hmac('sha256', $ip->keyOf($search)."\nroute:search", 'pepper'), $key->keyOf($search));
        self::assertNotSame($key->keyOf($search), $key->keyOf($login));
        self::assertSame('ip_route:'.hash_hmac('sha256', $ip->keyOf($unrouted)."\npath:/static/page", 'pepper'), $key->keyOf($unrouted));
        self::assertNull($key->keyOf($this->request(null)));
    }

    private function request(?string $address, string $uri = '/'): Request
    {
        $request = Request::create($uri, server: ['REMOTE_ADDR' => $address]);

        if (null === $address) {
            $request->server->remove('REMOTE_ADDR');
        }

        return $request;
    }
}
