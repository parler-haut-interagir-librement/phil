<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Benchmark;

use Generator;
use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\ResponseTagsListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Twig\CacheTagExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A listing template calls `cache_tag()` once per item it renders, and each tag is checked against a pattern before
 * it is kept, repeated ones included; the tags are then joined into a header on every main response.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"http-cache"})
 */
final class ResponseTagsBench
{
    private readonly ResponseTags $tags;

    private readonly CacheTagExtension $extension;

    private readonly ResponseTagsListener $listener;

    private readonly Request $request;

    private readonly HttpKernelInterface $kernel;

    /**
     * The tags of a product list: one per product, and its category, which repeats.
     *
     * @var list<array{string, string}>
     */
    private array $items = [];

    public function __construct()
    {
        $this->tags = new ResponseTags();
        $this->extension = new CacheTagExtension($this->tags);
        $this->listener = new ResponseTagsListener($this->tags);
        $this->request = Request::create('/products');
        $this->kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }

    /**
     * @param array{items: int} $params
     */
    public function setUp(array $params): void
    {
        for ($i = 0; $i < $params['items']; ++$i) {
            $this->items[] = [\sprintf('product:%d', 10_000 + $i), \sprintf('category:%d', $i % 12)];
        }
    }

    /**
     * What one page does: the templates tag every item, then the response leaves with its header.
     *
     * @ParamProviders("pages")
     */
    public function benchRenderAndRespond(): void
    {
        foreach ($this->items as [$product, $category]) {
            $this->extension->tag($product, $category);
        }

        $this->listener->onResponse(new ResponseEvent($this->kernel, $this->request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    /**
     * The validation alone: every value through the pattern, half of them repeated.
     *
     * @ParamProviders("pages")
     */
    public function benchList(): void
    {
        CacheTag::list(array_merge(...$this->items));
    }

    /**
     * @return Generator<string, array{items: int}>
     */
    public function pages(): Generator
    {
        yield '10 items' => ['items' => 10];
        yield '100 items' => ['items' => 100];
        yield '1 000 items' => ['items' => 1_000];
    }
}
