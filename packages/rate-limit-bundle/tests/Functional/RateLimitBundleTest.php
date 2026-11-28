<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional;

use LogicException;
use Override;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\RateLimitBundle\Tests\Functional\App\RecordingSubscriber;
use Phil\RateLimitBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The bundle in a real kernel: a controller limited by Symfony's attributes answered with its page in French, an
 * explicit key kept hashed, an API limited by its route default answered a problem in JSON, a limiter of the
 * framework's configuration answered with the page, a form limited by its name, each refusal dispatched.
 */
#[CoversClass(PhilRateLimitBundle::class)]
final class RateLimitBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $pool = self::getContainer()->get('cache.limits');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    public function testTheAttributesLimitTheControllerUntilItsPage(): void
    {
        foreach (['1', '0'] as $remaining) {
            $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '203.0.113.7']);

            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('RateLimit-Policy', '"search";q=2;w=60');
            self::assertStringStartsWith('"search";r='.$remaining.';t=', (string) $this->client->getResponse()->headers->get('RateLimit'));
        }

        $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertResponseHeaderSame('RateLimit-Policy', '"search";q=2;w=60');
        self::assertStringContainsString('<title>Trop de requêtes</title>', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('<html lang="fr">', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '198.51.100.2']);
        self::assertResponseIsSuccessful();

        $events = $this->subscriber()->events;
        self::assertCount(1, $events);
        self::assertSame('search', $events[0]->limiter);
        self::assertSame('search', $events[0]->route);
        self::assertStringStartsWith('ip_route:', $events[0]->key);
        self::assertStringNotContainsString('203.0.113.7', $events[0]->key);
    }

    public function testAnExplicitKeyIsHashedAndTheArgumentGetsTheLimit(): void
    {
        foreach (['2', '0'] as $remaining) {
            $this->client->request('GET', '/articles/42');

            self::assertResponseIsSuccessful();
            self::assertSame('42:'.$remaining, $this->client->getResponse()->getContent());
            self::assertResponseHeaderSame('RateLimit-Policy', '"articles";q=4;w=60');
        }

        $this->client->request('GET', '/articles/42');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('RateLimit-Policy', '"articles";q=4;w=60');

        $this->client->request('GET', '/articles/7');
        self::assertResponseIsSuccessful();

        $events = $this->subscriber()->events;
        self::assertCount(1, $events);
        self::assertSame('articles', $events[0]->limiter);
        self::assertSame('key:'.hash_hmac('sha256', '42', hash_hkdf('sha256', 'test', 32, 'phil_rate_limit.attribute_key')), $events[0]->key);

        $symfony = $this->subscriber()->symfony;
        self::assertCount(1, $symfony);
        self::assertSame('articles', $symfony[0]->getLimiterName());
        self::assertSame($events[0]->key, $symfony[0]->getKey());
        self::assertFalse($symfony[0]->getRateLimit()->isAccepted());
    }

    public function testAnAttributeWithoutKeyCountsByTheLimitersStrategy(): void
    {
        $this->client->request('GET', '/first', server: ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/second', server: ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(429);
        self::assertStringStartsWith('ip:', $this->subscriber()->events[0]->key);

        $this->client->request('GET', '/second', server: ['REMOTE_ADDR' => '198.51.100.2']);
        self::assertResponseIsSuccessful();
    }

    public function testTheRouteDefaultCountsBeforeTheAttributes(): void
    {
        $this->client->request('GET', '/ruled');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('RateLimit-Policy', '"api";q=1');

        $this->client->request('GET', '/ruled');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('RateLimit-Policy', '"api";q=1');
        self::assertSame(['api'], array_map(static fn (object $event): string => $event->limiter, $this->subscriber()->events));
    }

    public function testAFrameworkLimiterIsAnsweredWithThePage(): void
    {
        $this->client->request('GET', '/framework');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('RateLimit-Policy', '"burst";q=50;w=60');

        $this->client->request('GET', '/framework');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertResponseNotHasHeader('RateLimit-Policy');
        self::assertStringContainsString('<title>Trop de requêtes</title>', (string) $this->client->getResponse()->getContent());
        self::assertSame([], $this->subscriber()->events);
        self::assertSame('framework_limit', $this->subscriber()->symfony[0]->getLimiterName());
    }

    public function testTheRouteDefaultLimitsAnApiAnsweredInJson(): void
    {
        $this->client->request('GET', '/api');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('RateLimit-Policy', '"api";q=1');

        $this->client->request('GET', '/api');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame(['type' => 'about:blank', 'title' => 'Trop de requêtes', 'status' => 429], \array_slice($problem, 0, 3));
        self::assertGreaterThan(3000, $problem['retry_after']);
    }

    public function testALimiterInObservationLetsTheRequestsThrough(): void
    {
        for ($request = 0; $request < 3; ++$request) {
            $this->client->request('GET', '/watched');
            self::assertResponseIsSuccessful();
        }

        $subscriber = self::getContainer()->get(RecordingSubscriber::class);
        self::assertInstanceOf(RecordingSubscriber::class, $subscriber);
        self::assertSame([], $subscriber->events);
        self::assertCount(2, $subscriber->observed);
        self::assertSame('watched', $subscriber->observed[0]->limiter);
        self::assertSame('watched', $subscriber->observed[0]->route);
        self::assertFalse($subscriber->observed[0]->decision->accepted);
    }

    public function testAFormIsLimitedByItsName(): void
    {
        $this->client->request('POST', '/contact', ['contact' => []]);
        self::assertResponseIsSuccessful();
        self::assertSame('sent', $this->client->getResponse()->getContent());

        $this->client->request('POST', '/contact', ['contact' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringStartsWith('Ce formulaire a été envoyé trop souvent. Réessayez dans ', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        self::assertSame('contact', $this->subscriber()->events[0]->form);
    }

    public function testALocalPoolIsRefusedInProduction(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('.cache_pool: the store "cache.limits" is local to each server');

        $this->freshKernel('prod', false)->boot();
    }

    public function testAnUnknownLimiterStopsTheBuild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The rate limits of the route "unknown": No rate limiter is named "nothing"');

        $this->freshKernel('invalid', true)->boot();
    }

    /**
     * A kernel whose container is built again: a build expected to fail never meets one kept from an earlier run.
     */
    private function freshKernel(string $environment, bool $debug): TestKernel
    {
        $kernel = new TestKernel($environment, $debug);
        new Filesystem()->remove($kernel->getCacheDir());

        return $kernel;
    }

    private function subscriber(): RecordingSubscriber
    {
        $subscriber = self::getContainer()->get(RecordingSubscriber::class);
        self::assertInstanceOf(RecordingSubscriber::class, $subscriber);

        return $subscriber;
    }
}
