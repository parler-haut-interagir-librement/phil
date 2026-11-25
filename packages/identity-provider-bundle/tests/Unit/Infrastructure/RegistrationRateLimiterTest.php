<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Security\RegistrationRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(RegistrationRateLimiter::class)]
final class RegistrationRateLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    public function testAnIpv6NetworkSharesItsCount(): void
    {
        $limiter = $this->limiter(perIp: 2);

        self::assertTrue($limiter->allowsRegistration($this->request('2001:db8::1'), 'a@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('2001:db8::ffff'), 'b@example.com'));
        self::assertFalse($limiter->allowsRegistration($this->request('2001:db8:0:0:100::1'), 'c@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('2001:db8:0:1::1'), 'c@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.1'), 'd@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.2'), 'd@example.com'));
    }

    public function testAnIpv4AddressInIpv6FormKeepsItsOwnCount(): void
    {
        $limiter = $this->limiter(perIp: 1);

        self::assertTrue($limiter->allowsRegistration($this->request('::ffff:192.0.2.1'), 'a@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('::ffff:192.0.2.2'), 'b@example.com'));
        self::assertFalse($limiter->allowsRegistration($this->request('::ffff:192.0.2.2'), 'c@example.com'));
    }

    public function testAnAddressCountsWhateverItsCaseAndSpaces(): void
    {
        $limiter = $this->limiter(perAddress: 2);

        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.1'), 'ada@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.2'), ' ADA@Example.com '));
        self::assertFalse($limiter->allowsRegistration($this->request('192.0.2.3'), 'Ada@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.3'), 'grace@example.com'));
    }

    public function testAnAddressCountsWhateverTheCaseOfItsAccents(): void
    {
        $limiter = $this->limiter(perAddress: 1);

        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.1'), 'ÉLOÏSE@example.com'));
        self::assertFalse($limiter->allowsRegistration($this->request('192.0.2.2'), 'éloïse@example.com'));
    }

    public function testARefusalByTheClientSpendsNothingOfTheAddress(): void
    {
        $limiter = $this->limiter(perIp: 1, perAddress: 1);

        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.1'), 'ada@example.com'));
        self::assertFalse($limiter->allowsRegistration($this->request('192.0.2.1'), 'grace@example.com'));
        self::assertTrue($limiter->allowsRegistration($this->request('192.0.2.2'), 'grace@example.com'));
    }

    public function testARequestWithoutClientAddressIsCountedTogether(): void
    {
        $limiter = $this->limiter(perIp: 1);

        self::assertTrue($limiter->allowsRegistration(new Request(), 'ada@example.com'));
        self::assertFalse($limiter->allowsRegistration(new Request(), 'grace@example.com'));
    }

    public function testAVerificationEmailOnceInEachDelay(): void
    {
        $limiter = $this->limiter();

        self::assertTrue($limiter->allowsVerificationEmail('ada@example.com'));
        self::assertFalse($limiter->allowsVerificationEmail(' ADA@example.com'));
        self::assertTrue($limiter->allowsVerificationEmail('grace@example.com'));
    }

    public function testTheStoreHoldsNoAddress(): void
    {
        $limiter = $this->limiter();
        $limiter->allowsRegistration($this->request('192.0.2.1'), 'ada@example.com');
        $limiter->allowsVerificationEmail('ada@example.com');

        $keys = implode(' ', array_keys((array) new ReflectionProperty(InMemoryStorage::class, 'buckets')->getValue($this->storage)));
        self::assertStringNotContainsString('ada', $keys);
        self::assertStringNotContainsString('192.0.2', $keys);
        self::assertStringContainsString('registration.resend-', $keys);
    }

    public function testTheKeysDependOnTheSecret(): void
    {
        $this->limiter(perAddress: 1)->allowsRegistration($this->request('192.0.2.1'), 'ada@example.com');

        self::assertTrue($this->limiter(perAddress: 1, secret: 'another secret, long enough to sign')->allowsRegistration($this->request('192.0.2.2'), 'ada@example.com'));
    }

    public function testTheKeysAreDerivedFromTheSecretForThisUsageOnly(): void
    {
        $limiter = $this->limiter();
        $limiter->allowsRegistration($this->request('192.0.2.1'), ' Ada@example.com');
        $limiter->allowsVerificationEmail('ada@example.com');

        $key = hash_hkdf('sha256', 'a secret of the tests, long enough', 32, 'phil_identity_provider.registration_limiter');
        $hashed = static fn (string $part): string => sodium_bin2base64(hash_hmac('sha256', $part, $key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $keys = array_keys((array) new ReflectionProperty(InMemoryStorage::class, 'buckets')->getValue($this->storage));
        sort($keys);
        $expected = ['registration.address-'.$hashed('ada@example.com'), 'registration.ip-'.$hashed('192.0.2.1'), 'registration.resend-'.$hashed('ada@example.com')];
        sort($expected);

        self::assertSame($expected, $keys);
    }

    private function limiter(int $perIp = 10, int $perAddress = 10, string $secret = 'a secret of the tests, long enough'): RegistrationRateLimiter
    {
        $factory = fn (string $id, array $config): RateLimiterFactory => new RateLimiterFactory(['id' => 'registration.'.$id] + $config, $this->storage);

        return new RegistrationRateLimiter(
            $factory('ip', ['policy' => 'sliding_window', 'limit' => $perIp, 'interval' => '1 hour']),
            $factory('address', ['policy' => 'sliding_window', 'limit' => $perAddress, 'interval' => '1 hour']),
            $factory('resend', ['policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '1 hour', 'amount' => 1]]),
            $secret,
        );
    }

    private function request(string $address): Request
    {
        return new Request(server: ['REMOTE_ADDR' => $address]);
    }
}
