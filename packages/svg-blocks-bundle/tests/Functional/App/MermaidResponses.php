<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * What the Mermaid service answers in the tests: a diagram with a script the sanitizer removes, and the style it keeps.
 */
final class MermaidResponses
{
    /** @var list<array{string, string, string}> */
    public array $requests = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        $this->requests[] = [$method, $url, \is_string($options['body'] ?? null) ? $options['body'] : ''];

        return new MockResponse('<svg xmlns="http://www.w3.org/2000/svg"><style>.node{fill:#ececff;background:url(https://example.com/x.png)}</style><script>alert(1)</script><text>A</text></svg>', ['response_headers' => ['content-type' => 'image/svg+xml']]);
    }
}
