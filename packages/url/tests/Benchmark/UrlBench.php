<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Benchmark;

use Generator;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Every `url_display` and `url_type` filter parses its address again, and each messaging service parses a typed
 * handle as a link: an IDNA conversion and a few regular expressions per call, and a spoof check per display.
 *
 * @BeforeMethods("setUp")
 */
final class UrlBench
{
    private Url $url;

    public function __construct()
    {
        $this->url = Url::fromString('https://example.com/');
    }

    /**
     * @param array{text: string} $params
     */
    public function setUp(array $params): void
    {
        try {
            $this->url = Url::fromString($params['text']);
        } catch (UrlException) {
            // A refused address keeps the default one for the display subject.
        }
    }

    /**
     * @ParamProviders("addresses")
     *
     * @Revs(500)
     *
     * @param array{text: string} $params
     */
    public function benchFromString(array $params): void
    {
        try {
            Url::fromString($params['text']);
        } catch (UrlException) {
        }
    }

    /**
     * @ParamProviders("addresses")
     *
     * @Revs(500)
     */
    public function benchDisplay(): void
    {
        $this->url->display();
    }

    /**
     * @return Generator<string, array{text: string}>
     */
    public function addresses(): Generator
    {
        yield 'short' => ['text' => 'https://example.com'];
        yield 'typical' => ['text' => 'https://www.example.com/shop/products/blue-mug?colour=blue&size=large#reviews'];
        yield 'international' => ['text' => 'https://bücher.example/catalogue/livres'];
        yield 'long query' => ['text' => 'https://example.com/search?'.str_repeat('filter%5B%5D=value&', 100)];
        yield 'refused handle' => ['text' => 'https://ada_lovelace'];
    }
}
