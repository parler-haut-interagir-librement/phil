<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional;

use LogicException;
use Override;
use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\RateLimitBundle\Tests\Functional\App\RecordingSubscriber;
use Phil\RateLimitBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The bundle in a real kernel: a controller limited by its attributes answered with its page in French, an API limited
 * by its route default answered a problem in JSON, a form limited by its name, each refusal dispatched.
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

        $events = $this->events();
        self::assertCount(1, $events);
        self::assertSame('search', $events[0]->limiter);
        self::assertSame('search', $events[0]->route);
        self::assertStringStartsWith('ip_route:', $events[0]->key);
        self::assertStringNotContainsString('203.0.113.7', $events[0]->key);
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
        self::assertSame('contact', $this->events()[0]->form);
    }

    public function testALocalPoolIsRefusedInProduction(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('.cache_pool: the store "cache.limits" is local to each server');

        new TestKernel('prod', false)->boot();
    }

    public function testAnUnknownLimiterStopsTheBuild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The rate limits of the route "unknown": No rate limiter is named "nothing"');

        new TestKernel('invalid', true)->boot();
    }

    /**
     * @return list<RateLimitExceeded>
     */
    private function events(): array
    {
        $subscriber = self::getContainer()->get(RecordingSubscriber::class);
        self::assertInstanceOf(RecordingSubscriber::class, $subscriber);

        return $subscriber->events;
    }
}
