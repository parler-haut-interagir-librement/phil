<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application;

use Override;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\IndexingConsent\Reader;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Graph;
use Phil\StructuredData\LanguageTag;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\Event\CollectStructuredData;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

/**
 * The graph of the page being served. Controllers, templates and packages add nodes to it as the page is built; the
 * providers and the listeners of `CollectStructuredData` give theirs once, the first time the graph is asked for. The
 * nodes added win over those of the providers where both set a property: the page knows itself better than the site's
 * defaults.
 *
 * A page that shows a record whose owner withdrew it from search engines — `HasIndexingConsent` of
 * phil/indexing-consent — prints no structured data: the controller or the template names the record with `about()`,
 * or the request carries its `Indexing` under `IndexingPolicy::REQUEST_ATTRIBUTE`. The graph is still built, for the
 * share metadata of the page, which search engines do not read.
 *
 * A page is the main request: a fragment rendered by a sub-request adds to the graph of its page. What a page gathered
 * is held by its request, so it goes with it; outside a request — a command, a message handler — one graph is kept
 * until the service is reset, which the kernel does between two requests of a worker.
 */
final class PageStructuredData implements NodeCollector, ResetInterface
{
    /** @var WeakMap<Request, PageState> */
    private WeakMap $pages;

    private ?PageState $detached = null;

    /**
     * @param iterable<StructuredDataProvider> $providers
     * @param ?string                          $baseUrl         the address of the site, or `null` to take it from the request
     * @param ?string                          $language        the language of every graph, or `null`
     * @param bool                             $requestLanguage whether the graph is in the request's language when no `$language` is set
     */
    public function __construct(
        private readonly iterable $providers = [],
        private readonly ?RequestStack $requests = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $language = null,
        private readonly bool $requestLanguage = false,
    ) {
        $this->pages = new WeakMap();
    }

    #[Override]
    public function add(Node ...$nodes): void
    {
        $state = $this->state();
        $state->added = $state->added->with(...$nodes);
    }

    /**
     * Names the records the page shows: the page is as narrow as the strictest of them.
     */
    public function about(object ...$records): void
    {
        $state = $this->state();
        $state->indexing = Indexing::strictest($state->indexing, IndexingPolicy::ofAll(...array_values($records)));
    }

    /**
     * The reach of the page: the strictest of the records named by `about()` and of the `Indexing` its main request
     * carries, which the controller may set at any time.
     *
     * @phpstan-impure
     */
    public function indexing(): Indexing
    {
        $indexing = $this->state()->indexing;
        $carried = $this->requests?->getMainRequest()?->attributes->get(IndexingPolicy::REQUEST_ATTRIBUTE);

        if (null === $carried) {
            return $indexing;
        }

        // A value that is no `Indexing` is read as the narrowest: an error never puts a withdrawn record back in sight.
        $carried = $carried instanceof Indexing ? $carried : (\is_string($carried) ? Indexing::tryFrom($carried) : null);

        return Indexing::strictest($indexing, $carried ?? Indexing::Unlisted);
    }

    /**
     * Whether the page prints its structured data: whether `Reader::StructuredData` is allowed.
     *
     * @phpstan-impure
     */
    public function isPrintable(): bool
    {
        return $this->indexing()->allows(Reader::StructuredData);
    }

    public function context(): PageContext
    {
        return $this->state()->context;
    }

    /**
     * The graph of the page: the nodes of the providers and the listeners, then those added.
     */
    public function graph(): Graph
    {
        $state = $this->state();

        if (!$state->provided instanceof Graph) {
            $graph = Graph::of();

            foreach ($this->providers as $provider) {
                $graph = $graph->with(...$provider->nodes($state->context));
            }

            $event = new CollectStructuredData($state->context);
            $this->dispatcher?->dispatch($event);
            $state->provided = $graph->with(...$event->nodes());
        }

        return $state->provided->merge($state->added)->withLanguage($this->language ?? ($this->requestLanguage ? $state->context->locale : null));
    }

    #[Override]
    public function reset(): void
    {
        $this->pages = new WeakMap();
        $this->detached = null;
    }

    private function state(): PageState
    {
        $request = $this->requests?->getMainRequest();

        if (!$request instanceof Request) {
            return $this->detached ??= new PageState(new PageContext($this->baseUrl));
        }

        return $this->pages[$request] ??= new PageState($this->contextOf($request));
    }

    private function contextOf(Request $request): PageContext
    {
        $site = $request->getSchemeAndHttpHost().$request->getBasePath();
        $base = $this->baseUrl ?? $site.'/';

        try {
            $locale = LanguageTag::normalize($request->getLocale());
        } catch (InvalidStructuredData) {
            $locale = null;
        }

        return new PageContext($base, rtrim($base, '/').$request->getPathInfo(), $locale, $request);
    }
}
