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

        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.7', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key')), $key->keyOf($this->request('203.0.113.7')));
        self::assertNotSame($key->keyOf($this->request('203.0.113.7')), $key->keyOf($this->request('203.0.113.8')));
        self::assertNull($key->keyOf($this->request(null)));
    }

    public function testAnIpv6AddressCountsByItsSubscriberNetworkWithoutNormalizer(): void
    {
        $key = new ClientIpKey('secret');

        self::assertSame('ip:'.hash_hmac('sha256', '2001:db8:1:2::', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key')), $key->keyOf($this->request('2001:db8:1:2:aaaa::1')));
        self::assertSame($key->keyOf($this->request('2001:db8:1:2:aaaa::1')), $key->keyOf($this->request('2001:db8:1:2:bbbb::9')));
        self::assertNotSame($key->keyOf($this->request('2001:db8:1:2::1')), $key->keyOf($this->request('2001:db8:1:3::1')));
    }

    public function testTheNetworkSharesOneKey(): void
    {
        $key = new ClientIpKey('secret', new NetworkAddress(new Truncation(24, 64)));

        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.0', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key')), $key->keyOf($this->request('203.0.113.7')));
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

        self::assertSame('user:'.hash_hmac('sha256', 'ada@example.org', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.user_key')), $key->keyOf(Request::create('/')));
    }

    /**
     * One person counts once whatever the case, the accents, the compatibility forms or the invisible characters of
     * their identifier, as Symfony's login throttling counts them.
     */
    public function testTheUserIdentifierIsNormalizedBeforeItIsHashed(): void
    {
        $tokens = new TokenStorage();
        $key = new UserKey($tokens, 'secret');
        $keys = [];

        foreach (['ada@example.org', 'ADA@Example.org', "\u{00C0}da@example.org", "A\u{0300}da@example.org", "a\u{200B}da@example.org", " ada@example.org\t", "\u{FF41}da@example.org"] as $identifier) {
            $tokens->setToken(new UsernamePasswordToken(new InMemoryUser($identifier, null), 'main'));
            $keys[] = $key->keyOf(Request::create('/'));
        }

        self::assertSame(['user:'.hash_hmac('sha256', 'ada@example.org', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.user_key'))], array_values(array_unique($keys)));
    }

    public function testAnIpv4AddressWrittenAsIpv6CountsAsTheIpv4Address(): void
    {
        $key = new ClientIpKey('secret');
        $expected = 'ip:'.hash_hmac('sha256', '203.0.113.7', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key'));

        self::assertSame($expected, $key->keyOf($this->request('::ffff:203.0.113.7')));
        self::assertSame($expected, $key->keyOf($this->request('::FFFF:cb00:7107')));
        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.0', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key')), new ClientIpKey('secret', new NetworkAddress(new Truncation(24, 64)))->keyOf($this->request('::ffff:203.0.113.7')));
        self::assertSame('ip:'.hash_hmac('sha256', '::', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.client_ip_key')), $key->keyOf($this->request('::ffff:0:203.0.113.7')), 'an IPv4-translated address is IPv6');
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

        self::assertSame('ip_route:'.hash_hmac('sha256', $ip->keyOf($search)."\nroute:search", hash_hkdf('sha256', 'pepper', 32, 'phil_rate_limit.ip_route_key')), $key->keyOf($search));
        self::assertNotSame($key->keyOf($search), $key->keyOf($login));
        self::assertSame('ip_route:'.hash_hmac('sha256', $ip->keyOf($unrouted)."\npath:/static/page", hash_hkdf('sha256', 'pepper', 32, 'phil_rate_limit.ip_route_key')), $key->keyOf($unrouted));
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
