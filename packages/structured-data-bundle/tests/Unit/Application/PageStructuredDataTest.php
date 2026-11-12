<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Application;

use Locale;
use Phil\IndexingConsent\Contract\HasIndexingConsent;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\Event\CollectStructuredData;
use Phil\StructuredDataBundle\Application\PageContext;
use Phil\StructuredDataBundle\Application\PageState;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PageStructuredData::class)]
#[CoversClass(PageState::class)]
#[CoversClass(PageContext::class)]
#[CoversClass(CollectStructuredData::class)]
final class PageStructuredDataTest extends TestCase
{
    public function testTheGraphGathersProvidersListenersAndAddedNodes(): void
    {
        $provider = new class implements StructuredDataProvider {
            public int $calls = 0;

            public function nodes(PageContext $page): iterable
            {
                ++$this->calls;

                return [Node::of('WebPage', ['url' => $page->url, 'name' => 'Default'], $page->pageId('webpage'))];
            }
        };
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof CollectStructuredData) {
                    $event->add(Node::of('Thing', ['name' => 'listened']), Node::of('Thing', ['name' => 'heard']));
                }

                return $event;
            }
        };
        $requests = new RequestStack([Request::create('https://example.com/shop/a?x=1')]);
        $page = new PageStructuredData([$provider], $requests, $dispatcher);

        $page->add(Node::of('WebPage', ['name' => 'Page A'], 'https://example.com/shop/a#webpage'));

        $first = $page->graph();
        $page->add(Node::of('Thing', ['name' => 'later']));

        self::assertSame([
            ['@type' => 'WebPage', '@id' => 'https://example.com/shop/a#webpage', 'url' => 'https://example.com/shop/a', 'name' => 'Page A'],
            ['@type' => 'Thing', 'name' => 'listened'],
            ['@type' => 'Thing', 'name' => 'heard'],
        ], $first->toArray()['@graph']);
        self::assertCount(4, $page->graph());
        self::assertSame(1, $provider->calls);
        self::assertSame('https://example.com/', $page->context()->baseUrl);
        self::assertSame('https://example.com/shop/a', $page->context()->url);
        self::assertSame('en', $page->context()->locale);
        self::assertSame($requests->getMainRequest(), $page->context()->request);
        self::assertNull($page->graph()->language);
    }

    public function testEachPageHasItsOwnGraphAndAFragmentAddsToItsPage(): void
    {
        $first = Request::create('https://example.com/a');
        $requests = new RequestStack([$first]);
        $page = new PageStructuredData(requests: $requests);
        $page->add(Node::of('Thing', ['name' => 'a']));

        $requests->push(Request::create('https://example.com/_fragment'));
        $page->add(Node::of('Thing', ['name' => 'fragment']));
        self::assertCount(2, $page->graph());
        $requests->pop();
        $requests->pop();

        $requests->push(Request::create('https://example.com/b'));
        self::assertTrue($page->graph()->isEmpty());
        $page->add(Node::of('Thing', ['name' => 'b']));
        $requests->pop();
        $requests->push($first);
        self::assertCount(2, $page->graph());
    }

    public function testOutsideARequestOneGraphIsKeptUntilReset(): void
    {
        $page = new PageStructuredData(baseUrl: 'https://example.com/');
        $page->add(Node::of('Thing', ['name' => 'a']));

        self::assertCount(1, $page->graph());
        self::assertSame('https://example.com/', $page->context()->baseUrl);
        self::assertNull($page->context()->url);
        self::assertNull($page->context()->request);
        self::assertNull($page->context()->pageId('webpage'));
        self::assertSame('https://example.com/#website', $page->context()->siteId('website'));
        self::assertNull(new PageContext()->siteId('website'));

        $page->reset();
        self::assertTrue($page->graph()->isEmpty());
        self::assertTrue(new PageStructuredData(requests: new RequestStack())->graph()->isEmpty());
    }

    public function testResetForgetsThePagesOfRequestsToo(): void
    {
        $requests = new RequestStack([Request::create('https://example.com/a')]);
        $page = new PageStructuredData(requests: $requests);
        $page->add(Node::of('Thing'));

        $page->reset();

        self::assertTrue($page->graph()->isEmpty());
    }

    public function testTheConfiguredAddressOfTheSiteWinsOverTheRequest(): void
    {
        $request = Request::create('http://internal:8080/index.php/fr/a', server: ['SCRIPT_FILENAME' => '/app/index.php', 'SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '/index.php']);
        $requests = new RequestStack([$request]);

        $context = new PageStructuredData(requests: $requests, baseUrl: 'https://example.com/')->context();
        self::assertSame('https://example.com/', $context->baseUrl);
        self::assertSame('https://example.com/fr/a', $context->url);

        $context = new PageStructuredData(requests: $requests)->context();
        self::assertSame('http://internal:8080/', $context->baseUrl);
        self::assertSame('http://internal:8080/fr/a', $context->url);

        $context = new PageStructuredData(requests: new RequestStack([Request::create('https://example.com/shop/index.php/fr/a', server: ['SCRIPT_FILENAME' => '/app/shop/index.php', 'SCRIPT_NAME' => '/shop/index.php', 'PHP_SELF' => '/shop/index.php'])]))->context();
        self::assertSame('https://example.com/shop/', $context->baseUrl);
        self::assertSame('https://example.com/shop/fr/a', $context->url);
    }

    public function testTheGraphIsInTheConfiguredLanguageOrTheRequestOne(): void
    {
        // A request's locale is also PHP's default: it is put back for the tests that follow.
        $default = Locale::getDefault();

        try {
            $request = Request::create('https://example.com/a');
            $request->setLocale('fr_CA');

            $requests = new RequestStack([$request]);
            $added = Node::of('Thing');

            $page = new PageStructuredData(requests: $requests, language: 'en', requestLanguage: true);
            $page->add($added);
            self::assertSame('en', $page->graph()->language);

            $page = new PageStructuredData(requests: $requests, requestLanguage: true);
            $page->add($added);
            self::assertSame('fr-CA', $page->graph()->language);
            self::assertSame('fr-CA', $page->context()->locale);

            $request->setLocale('root');
            $page = new PageStructuredData(requests: $requests, requestLanguage: true);
            self::assertNull($page->graph()->language);
            self::assertNull($page->context()->locale);
        } finally {
            Locale::setDefault($default);
        }
    }

    public function testThePageIsAsNarrowAsTheRecordsItShows(): void
    {
        $page = new PageStructuredData();
        self::assertSame(Indexing::Listed, $page->indexing());
        self::assertTrue($page->isPrintable());

        $page->about(new stdClass());
        self::assertTrue($page->isPrintable());
        $page->about($this->record(Indexing::NotIndexed), new stdClass());
        self::assertSame(Indexing::NotIndexed, $page->indexing());
        self::assertFalse($page->isPrintable());
        $page->about($this->record(Indexing::Listed));
        self::assertSame(Indexing::NotIndexed, $page->indexing(), 'A wider record never widens the page.');
        $page->about($this->record(Indexing::Unlisted));
        self::assertSame(Indexing::Unlisted, $page->indexing());

        $page->reset();
        self::assertSame(Indexing::Listed, $page->indexing());
        self::assertSame(Indexing::Listed, new PageStructuredData(requests: new RequestStack())->indexing(), 'A stack without a request carries nothing.');
    }

    public function testTheRequestCarriesTheReachOfItsRecord(): void
    {
        $request = Request::create('https://example.com/a');
        $page = new PageStructuredData(requests: new RequestStack([$request]));
        self::assertSame(Indexing::Listed, $page->indexing());

        $request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, Indexing::NotIndexed);
        self::assertSame(Indexing::NotIndexed, $page->indexing());
        self::assertFalse($page->isPrintable());
        $page->about($this->record(Indexing::Unlisted));
        self::assertSame(Indexing::Unlisted, $page->indexing());

        $other = Request::create('https://example.com/b');
        $page = new PageStructuredData(requests: new RequestStack([$other]));
        $other->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, 'listed');
        self::assertSame(Indexing::Listed, $page->indexing());
        $other->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, 'not_indexed');
        self::assertSame(Indexing::NotIndexed, $page->indexing());
        $other->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, 'hidden');
        self::assertSame(Indexing::Unlisted, $page->indexing(), 'A value nobody wrote is read as the narrowest.');
        $other->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, 42);
        self::assertSame(Indexing::Unlisted, $page->indexing());
        $other->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, null);
        self::assertSame(Indexing::Listed, $page->indexing());
    }

    private function record(Indexing $indexing): HasIndexingConsent
    {
        return new readonly class($indexing) implements HasIndexingConsent {
            public function __construct(private Indexing $choice)
            {
            }

            public function indexing(): Indexing
            {
                return $this->choice;
            }
        };
    }
}
