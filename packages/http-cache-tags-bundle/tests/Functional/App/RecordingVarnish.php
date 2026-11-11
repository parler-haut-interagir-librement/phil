<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The Varnish of the tests: it writes down the tags it is asked to purge.
 */
final class RecordingVarnish
{
    /** @var list<string> */
    public array $purged = [];

    /**
     * @param array{headers?: list<string>} $options
     */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        foreach ($options['headers'] ?? [] as $line) {
            if (str_starts_with($line, 'xkey-purge: ')) {
                $this->purged[] = substr($line, \strlen('xkey-purge: '));
            }
        }

        return new MockResponse('', ['http_code' => 200]);
    }
}
