<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use Override;
use Phil\NewsletterBundle\Infrastructure\RateLimiter\SubscriptionRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(SubscriptionRateLimiter::class)]
final class SubscriptionRateLimiterTest extends TestCase
{
    private const string SECRET = 'a-secret-of-sixteen-bytes';

    /** @var ArrayObject<int, string> */
    private ArrayObject $keys;

    #[Override]
    protected function setUp(): void
    {
        $this->keys = new ArrayObject();
    }

    public function testAClientPostsAsManySubscriptionsAsItsLimitThenItsNetworkIsRefused(): void
    {
        $limiter = $this->limiter(perIp: 2);

        self::assertTrue($limiter->allowsSubscription($this->request('2001:db8:1:2::1'), 'default', 'a@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('2001:db8:1:2::ff'), 'default', 'b@example.com'), 'The same /64 network.');
        self::assertFalse($limiter->allowsSubscription($this->request('2001:db8:1:2:abcd::1'), 'default', 'c@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('2001:db8:1:3::1'), 'default', 'c@example.com'), 'Another /64 network.');
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'd@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'e@example.com'));
        self::assertFalse($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'f@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.2'), 'default', 'f@example.com'), 'An IPv4 address is not a network.');
    }

    public function testAnIpv4AddressMappedInIpv6IsNotAnonymized(): void
    {
        $limiter = $this->limiter(perIp: 1);

        self::assertTrue($limiter->allowsSubscription($this->request('::ffff:192.0.2.1'), 'default', 'a@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('::ffff:192.0.2.2'), 'default', 'b@example.com'), 'A mapped IPv4 address is not a network.');
        self::assertFalse($limiter->allowsSubscription($this->request('::ffff:192.0.2.1'), 'default', 'c@example.com'));
    }

    public function testAnAddressIsLowercasedAsUnicode(): void
    {
        $limiter = $this->limiter(perAddress: 1);

        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'ÉLODIE@example.com'));
        self::assertFalse($limiter->allowsSubscription($this->request('192.0.2.2'), 'default', 'élodie@example.com'));
    }

    public function testAnAddressIsSubscribedAsOftenAsItsLimitWhateverItsSpellingOrClient(): void
    {
        $limiter = $this->limiter(perAddress: 2);

        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'ada@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.2'), 'default', ' ADA@Example.com '));
        self::assertFalse($limiter->allowsSubscription($this->request('192.0.2.3'), 'default', 'Ada@example.COM'));
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.3'), 'shop', 'ada@example.com'), 'Each site counts its own.');
    }

    public function testARefusalByTheClientSpendsNothingOfTheAddress(): void
    {
        $limiter = $this->limiter(perIp: 1, perAddress: 1);

        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'a@example.com'));
        self::assertFalse($limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'b@example.com'));
        self::assertTrue($limiter->allowsSubscription($this->request('192.0.2.2'), 'default', 'b@example.com'));
    }

    public function testARequestWithoutAClientAddressSharesOneCount(): void
    {
        $limiter = $this->limiter(perIp: 1);
        $request = new Request();
        $request->server->remove('REMOTE_ADDR');

        self::assertTrue($limiter->allowsSubscription($request, 'default', 'a@example.com'));
        self::assertFalse($limiter->allowsSubscription($request, 'default', 'b@example.com'));
    }

    public function testOneConfirmationGoesToASubscriberInEachDelay(): void
    {
        $limiter = $this->limiter();

        self::assertTrue($limiter->allowsConfirmation('subscriber-1'));
        self::assertFalse($limiter->allowsConfirmation('subscriber-1'));
        self::assertTrue($limiter->allowsConfirmation('subscriber-2'));
    }

    public function testTheKeysAreKeyedHashesThatNameNoAddress(): void
    {
        $limiter = $this->limiter();
        $limiter->allowsSubscription($this->request('192.0.2.1'), 'default', 'Ada@example.com');
        $limiter->allowsConfirmation('subscriber-1');

        $hash = static fn (string $part): string => sodium_bin2base64(hash_hmac('sha256', $part, self::SECRET, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        self::assertSame(['ip:'.$hash('192.0.2.1'), 'address:'.$hash("default\nada@example.com"), 'resend:'.$hash('subscriber-1')], $this->keys->getArrayCopy());

        foreach ($this->keys as $key) {
            self::assertStringNotContainsString('ada', strtolower($key));
            self::assertStringNotContainsString('192.0.2.1', $key);
        }
    }

    private function limiter(int $perIp = 10, int $perAddress = 5): SubscriptionRateLimiter
    {
        $storage = new InMemoryStorage();

        return new SubscriptionRateLimiter(
            $this->recorded('ip', new RateLimiterFactory(['id' => 'ip', 'policy' => 'sliding_window', 'limit' => $perIp, 'interval' => '1 hour'], $storage)),
            $this->recorded('address', new RateLimiterFactory(['id' => 'address', 'policy' => 'sliding_window', 'limit' => $perAddress, 'interval' => '1 hour'], $storage)),
            $this->recorded('resend', new RateLimiterFactory(['id' => 'resend', 'policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '5 minutes', 'amount' => 1]], $storage)),
            self::SECRET,
        );
    }

    private function recorded(string $name, RateLimiterFactoryInterface $factory): RateLimiterFactoryInterface
    {
        return new readonly class($name, $factory, $this->keys) implements RateLimiterFactoryInterface {
            /**
             * @param ArrayObject<int, string> $keys
             */
            public function __construct(
                private string $name,
                private RateLimiterFactoryInterface $factory,
                private ArrayObject $keys,
            ) {
            }

            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                $this->keys->append($this->name.':'.$key);

                return $this->factory->create($key);
            }
        };
    }

    private function request(string $address): Request
    {
        return Request::create('/newsletter/subscribe/news', 'POST', server: ['REMOTE_ADDR' => $address]);
    }
}
