<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Unit\Infrastructure;

use Phil\HttpCacheTagsBundle\Application\CacheTagPurger;
use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\HttpCacheTagsBundle\Domain\Exception\InvalidCacheTag;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\CacheTag;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\InvalidationFlushListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\ResponseTagsListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\PurgeFailed;
use Phil\HttpCacheTagsBundle\Infrastructure\Twig\CacheTagExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(CacheTag::class)]
#[CoversClass(ResponseTagsListener::class)]
#[CoversClass(InvalidationFlushListener::class)]
#[CoversClass(CacheTagExtension::class)]
final class HttpTest extends TestCase
{
    public function testAControllersTagsAreWrittenIntoTheHeader(): void
    {
        $tags = new ResponseTags();
        $listener = new ResponseTagsListener($tags, 'Cache-Tag', ',');
        $request = Request::create('/pages/42');
        $request->attributes->add(['id' => 42, 'slug' => 'about', 'list' => ['x']]);

        $listener->onController($this->controller($request, [new CacheTag('page:{id}', 'page:{id}:{slug}', 'menu'), new CacheTag('missing:{nope}', 'list:{list}', 'site')]));
        new CacheTagExtension($tags)->tag('footer', 'menu');
        self::assertSame('', new CacheTagExtension($tags)->tag());

        $response = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame('page:42,page:42:about,menu,site,footer', $response->headers->get('Cache-Tag'));
        self::assertSame([], $tags->all());

        $untagged = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $untagged));
        self::assertFalse($untagged->headers->has('Cache-Tag'));
    }

    public function testARouteValueThatMakesNoTagIsLeftOut(): void
    {
        $tags = new ResponseTags();
        $listener = new ResponseTagsListener($tags);
        $request = Request::create('/page/café');
        $request->attributes->add(['slug' => 'café', 'id' => 'a b', 'long' => str_repeat('x', 200), 'ok' => 'about']);

        $listener->onController($this->controller($request, [new CacheTag('page:{slug}', 'page:{id}', 'page:{long}', 'page:{ok}', 'menu')]));

        self::assertSame(['page:about', 'menu'], $tags->all());

        // A value that repeats the placeholder is still a visitor's value, and makes no tag.
        $literal = Request::create('/page/%7Bslug%7D');
        $literal->attributes->set('slug', '{slug}');

        $tags->reset();
        $listener->onController($this->controller($literal, [new CacheTag('page:{slug}')]));
        self::assertSame([], $tags->all());

        $this->expectException(InvalidCacheTag::class);
        $listener->onController($this->controller($request, [new CacheTag('a tag the developer wrote wrong')]));
    }

    public function testASubRequestKeepsItsTagsForTheMainOne(): void
    {
        $tags = new ResponseTags();
        $tags->add('fragment');

        $listener = new ResponseTagsListener($tags);
        $sub = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::SUB_REQUEST, $sub));

        self::assertFalse($sub->headers->has('xkey'));
        self::assertSame(['fragment'], $tags->all());

        $tags->add('page');
        $main = new Response();
        $listener->onResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $main));
        self::assertSame('fragment page', $main->headers->get('xkey'));
    }

    public function testTheEndOfARequestPurges(): void
    {
        $invalidator = new Invalidator(null);
        $invalidator->invalidate('page:1');
        new InvalidationFlushListener($invalidator)();

        self::assertSame([], $invalidator->pending());
    }

    public function testARefusedPurgeIsLoggedOrThrown(): void
    {
        $refusing = new class implements CacheTagPurger {
            public function purge(array $tags): void
            {
                throw PurgeFailed::of(['Varnish at http://varnish-1 answered the purge with 503.']);
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $logs = [];

            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
        $invalidator = new Invalidator($refusing);
        $invalidator->invalidate('page:1');
        new InvalidationFlushListener($invalidator, $logger)();

        self::assertCount(1, $logger->logs);
        self::assertSame([LogLevel::ERROR, 'The cache tags were not purged: {reason}', 'Varnish at http://varnish-1 answered the purge with 503.'], [$logger->logs[0][0], $logger->logs[0][1], $logger->logs[0][2]['reason'] ?? null]);
        self::assertInstanceOf(PurgeFailed::class, $logger->logs[0][2]['exception'] ?? null);
        self::assertSame([], $invalidator->pending());

        $invalidator->invalidate('page:2');
        $this->expectException(PurgeFailed::class);
        new InvalidationFlushListener($invalidator)();
    }

    /**
     * @param list<CacheTag> $attributes
     */
    private function controller(Request $request, array $attributes): ControllerEvent
    {
        $event = new ControllerEvent(self::createStub(HttpKernelInterface::class), static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($event->getController(), $attributes);

        return $event;
    }
}
