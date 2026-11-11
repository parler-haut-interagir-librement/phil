<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Unit\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Phil\HttpCacheTagsBundle\Application\CacheTagResolver;
use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;
use Phil\HttpCacheTagsBundle\Infrastructure\Doctrine\InvalidateOnFlushListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\CloudflarePurger;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\PurgeFailed;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\VarnishPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[CoversClass(VarnishPurger::class)]
#[CoversClass(CloudflarePurger::class)]
#[CoversClass(PurgeFailed::class)]
#[CoversClass(InvalidateOnFlushListener::class)]
final class PurgersTest extends TestCase
{
    public function testVarnishIsPurgedByXkey(): void
    {
        $sent = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent[] = [$method, $url, $options['headers']];

            return new MockResponse('', ['http_code' => 200]);
        });
        new VarnishPurger($client, ['http://varnish-1', 'http://varnish-2'])->purge([CacheTag::of('page:1'), CacheTag::of('menu')]);
        new VarnishPurger($client, ['http://varnish-1'], 'xkey-softpurge')->purge([CacheTag::of('a')]);

        self::assertSame([['PURGE', 'http://varnish-1/', ['xkey-purge: page:1 menu']], ['PURGE', 'http://varnish-2/', ['xkey-purge: page:1 menu']], ['PURGE', 'http://varnish-1/', ['xkey-softpurge: a']]], array_map(static fn (array $request): array => [$request[0], $request[1], array_values(array_filter((array) $request[2], static fn (mixed $line): bool => \is_string($line) && str_starts_with($line, 'xkey')))], $sent));

        foreach ([399 => false, 400 => true] as $status => $fails) {
            try {
                new VarnishPurger(new MockHttpClient(new MockResponse('', ['http_code' => $status])), ['http://varnish-1'])->purge([CacheTag::of('a')]);
                self::assertFalse($fails);
            } catch (PurgeFailed $purgeFailed) {
                self::assertTrue($fails);
                self::assertSame('Varnish at http://varnish-1 answered the purge with 400.', $purgeFailed->getMessage());
            }
        }
    }

    public function testEveryVarnishIsPurgedThoughOneFails(): void
    {
        $sent = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$sent): MockResponse {
            $sent[] = $url;

            return match ($url) {
                'http://varnish-1/' => new MockResponse('', ['http_code' => 503]),
                'http://varnish-2/' => new MockResponse('', ['error' => 'Connection refused']),
                'http://varnish-3/' => new MockResponse('', ['error' => 'Connection timed out.']),
                default => new MockResponse('', ['http_code' => 200]),
            };
        });

        try {
            new VarnishPurger($client, ['http://varnish-1', 'http://varnish-2', 'http://varnish-3', 'http://varnish-4'])->purge([CacheTag::of('a')]);
            self::fail('A failed purge went unnoticed.');
        } catch (PurgeFailed $purgeFailed) {
            self::assertSame('Varnish at http://varnish-1 answered the purge with 503. Varnish at http://varnish-2 could not be reached: Connection refused. Varnish at http://varnish-3 could not be reached: Connection timed out.', $purgeFailed->getMessage());
            self::assertInstanceOf(TransportExceptionInterface::class, $purgeFailed->getPrevious());
            self::assertSame('Connection refused', $purgeFailed->getPrevious()->getMessage(), 'the first error is kept');
        }

        self::assertSame(['http://varnish-1/', 'http://varnish-2/', 'http://varnish-3/', 'http://varnish-4/'], $sent);
    }

    public function testEveryCloudflareCallIsMadeThoughOneFails(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return match ($calls) {
                1 => new MockResponse('', ['error' => 'Timeout']),
                2 => new MockResponse('', ['http_code' => 429]),
                3 => new MockResponse('', ['error' => 'Connection reset']),
                default => new MockResponse('{"success":true}', ['http_code' => 200]),
            };
        });

        try {
            new CloudflarePurger($client, 'z', 't')->purge(array_map(static fn (int $n): CacheTag => CacheTag::of('t'.$n), range(1, 350)));
            self::fail('A failed purge went unnoticed.');
        } catch (PurgeFailed $purgeFailed) {
            self::assertSame('Cloudflare could not be reached: Timeout. Cloudflare, for tags 101 to 200, answered the purge with 429. Cloudflare, for tags 201 to 300, could not be reached: Connection reset.', $purgeFailed->getMessage());
            self::assertSame('Timeout', $purgeFailed->getPrevious()?->getMessage(), 'the first error is kept');
        }

        self::assertSame(4, $calls);
    }

    public function testCloudflareIsPurgedAHundredTagsAtATime(): void
    {
        $sent = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $headers = $options['normalized_headers'] ?? [];
            self::assertIsArray($headers);
            $authorization = $headers['authorization'] ?? [];
            self::assertIsArray($authorization);
            self::assertIsString($options['body'] ?? null);
            $sent[] = [$method, $url, $options['body'], $authorization[0] ?? null];

            return new MockResponse('{"success":true}', ['http_code' => 200]);
        });
        $tags = array_map(static fn (int $n): CacheTag => CacheTag::of('t'.$n), range(1, 101));
        new CloudflarePurger($client, 'zone/1', 'secret')->purge($tags);

        self::assertCount(2, $sent);
        self::assertSame(['POST', 'https://api.cloudflare.com/client/v4/zones/zone%2F1/purge_cache', 'Authorization: Bearer secret'], [$sent[0][0], $sent[0][1], $sent[0][3]]);
        self::assertSame(['tags' => array_map(static fn (int $n): string => 't'.$n, range(1, 100))], json_decode($sent[0][2], true));
        self::assertSame(['tags' => ['t101']], json_decode($sent[1][2], true));
        self::assertSame(100, CloudflarePurger::TAGS_PER_CALL);

        $this->expectException(PurgeFailed::class);
        $this->expectExceptionMessageIsOrContains('Cloudflare answered the purge with 403.');

        new CloudflarePurger(new MockHttpClient(new MockResponse('', ['http_code' => 403])), 'z', 't')->purge([CacheTag::of('a')]);
    }

    public function testCloudflareAcceptsUpTo399(): void
    {
        new CloudflarePurger(new MockHttpClient(new MockResponse('', ['http_code' => 399])), 'z', 't')->purge([CacheTag::of('a')]);

        $this->expectException(PurgeFailed::class);
        $this->expectExceptionMessageIsOrContains('Cloudflare answered the purge with 400.');

        new CloudflarePurger(new MockHttpClient(new MockResponse('', ['http_code' => 400])), 'z', 't')->purge([CacheTag::of('a')]);
    }

    public function testAFlushInvalidatesWhatItChanges(): void
    {
        $resolver = new class implements CacheTagResolver {
            public function tagsFor(object $entity): iterable
            {
                return $entity instanceof stdClass && \is_string($entity->tag ?? null) ? [$entity->tag] : [];
            }
        };
        $invalidator = new Invalidator(null, [$resolver]);
        $entity = static function (string $tag): stdClass {
            $object = new stdClass();
            $object->tag = $tag;

            return $object;
        };
        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn(['a' => $entity('new')]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn(['b' => $entity('changed')]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn(['c' => $entity('gone')]);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        new InvalidateOnFlushListener($invalidator)->onFlush(new OnFlushEventArgs($entityManager));

        self::assertSame(['new', 'changed', 'gone'], $invalidator->pending());
    }
}
