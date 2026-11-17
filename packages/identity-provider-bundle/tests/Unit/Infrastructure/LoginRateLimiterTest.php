<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Infrastructure\Security\LoginRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

#[CoversClass(LoginRateLimiter::class)]
final class LoginRateLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    public function testOneIdentifierIsLimitedWhateverTheAddressItComesFrom(): void
    {
        $limiter = $this->limiter(network: 100, local: 100, account: 3);

        foreach (['192.0.2.1', '198.51.100.7', '203.0.113.9'] as $address) {
            self::assertTrue($limiter->consume($this->request('ada@example.com', $address))->isAccepted());
        }

        self::assertFalse($limiter->consume($this->request(' ADA@example.com ', '192.0.2.200'))->isAccepted(), 'The case and the spaces of the identifier make no new limit.');
        self::assertTrue($limiter->consume($this->request('bob@example.com', '192.0.2.200'))->isAccepted(), 'Another identifier has a limit of its own.');
        self::assertSame(0, $limiter->peek($this->request('ada@example.com', '192.0.2.201'))->getRemainingTokens());
    }

    public function testTheLimitsPerIdentifierAndAddressAndPerAddressStay(): void
    {
        $limiter = $this->limiter(network: 3, local: 2, account: 100);

        self::assertTrue($limiter->consume($this->request('ada@example.com', '192.0.2.1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('ada@example.com', '192.0.2.1'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('ada@example.com', '192.0.2.1'))->isAccepted(), 'Per identifier and address.');
        self::assertFalse($limiter->consume($this->request('bob@example.com', '192.0.2.1'))->isAccepted(), 'Per address.');
        self::assertTrue($limiter->consume($this->request('bob@example.com', '192.0.2.2'))->isAccepted());
    }

    public function testTheCountPerIdentifierAndAddressIsKeptForEachAddress(): void
    {
        $limiter = $this->limiter(network: 100, local: 2, account: 100);

        self::assertTrue($limiter->consume($this->request('ada@example.com', '192.0.2.1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('ada@example.com', '192.0.2.1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('ada@example.com', '198.51.100.7'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('ada@example.com', '198.51.100.7'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('ada@example.com', '198.51.100.7'))->isAccepted());
    }

    public function testAnIdentifierIsLowercasedBeyondAscii(): void
    {
        $limiter = $this->limiter(network: 100, local: 100, account: 1);

        self::assertTrue($limiter->consume($this->request('ÉLODIE@example.com', '192.0.2.1'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('élodie@example.com', '192.0.2.2'))->isAccepted());
    }

    public function testAnIpv6NetworkIsItsFirstSixtyFourBits(): void
    {
        $limiter = $this->limiter(network: 1, local: 100, account: 100);

        self::assertTrue($limiter->consume($this->request('a@example.com', '2001:db8::1'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('b@example.com', '2001:db8::100:0:0:1'))->isAccepted(), 'The ninth byte is in the same /64.');
        self::assertTrue($limiter->consume($this->request('c@example.com', '2001:db8:0:1::1'))->isAccepted(), 'The eighth byte is another /64.');
    }

    public function testAnIpv4AddressMappedInIpv6IsCountedWhole(): void
    {
        $limiter = $this->limiter(network: 1, local: 100, account: 100);

        self::assertTrue($limiter->consume($this->request('a@example.com', '::ffff:192.0.2.1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('b@example.com', '::ffff:192.0.2.2'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('c@example.com', '::ffff:192.0.2.2'))->isAccepted());
    }

    public function testAnIpv6ClientIsCountedByItsNetwork(): void
    {
        $limiter = $this->limiter(network: 2, local: 100, account: 100);

        self::assertTrue($limiter->consume($this->request('a@example.com', '2001:db8::1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('b@example.com', '2001:db8::ffff:2'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('c@example.com', '2001:db8::3'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('c@example.com', '2001:db8:0:1::3'))->isAccepted());
    }

    public function testASuccessClearsTheLimitsOfTheIdentifierAndGivesItsTokenBackToTheAddress(): void
    {
        $limiter = $this->limiter(network: 3, local: 2, account: 2);
        $request = $this->request('ada@example.com', '192.0.2.1');
        $limiter->consume($request);
        $limiter->consume($request);
        self::assertSame(0, $limiter->peek($request)->getRemainingTokens());

        $limiter->reset($request);

        self::assertSame(2, $limiter->peek($request)->getRemainingTokens());
        self::assertTrue($limiter->consume($this->request('bob@example.com', '192.0.2.1'))->isAccepted());
        self::assertTrue($limiter->consume($this->request('carol@example.com', '192.0.2.1'))->isAccepted());
        self::assertFalse($limiter->consume($this->request('dave@example.com', '192.0.2.1'))->isAccepted(), 'The address keeps what the other identifiers spent.');
    }

    public function testTheKeysNeverHoldTheIdentifierNorTheAddress(): void
    {
        $limiter = $this->limiter(network: 5, local: 5, account: 5);
        $limiter->consume($this->request('ada@example.com', '192.0.2.1'));

        $keys = array_keys((array) new ReflectionProperty(InMemoryStorage::class, 'buckets')->getValue($this->storage));

        self::assertCount(3, $keys);

        foreach ($keys as $key) {
            self::assertStringNotContainsString('ada', (string) $key);
            self::assertStringNotContainsString('192.0.2.1', (string) $key);
        }
    }

    public function testARequestWithoutIdentifierIsCountedByItsAddress(): void
    {
        $limiter = $this->limiter(network: 1, local: 100, account: 100);
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '192.0.2.1']);

        self::assertTrue($limiter->consume($request)->isAccepted());
        self::assertFalse($limiter->consume($request)->isAccepted());
    }

    private function limiter(int $network, int $local, int $account): LoginRateLimiter
    {
        $factory = fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '15 minutes'], $this->storage);

        return new LoginRateLimiter($factory('network', $network), $factory('local', $local), $factory('account', $account), 'a-secret-of-at-least-32-characters-long');
    }

    private function request(string $identifier, string $address): Request
    {
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => $address]);
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        return $request;
    }
}
