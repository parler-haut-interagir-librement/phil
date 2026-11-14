<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The HTTP client of the test application: it answers every webhook `204`, and keeps what was posted.
 */
final class Webhooks extends MockHttpClient
{
    /** @var list<array{string, string, string}> */
    public array $posted = [];

    public function __construct()
    {
        parent::__construct(function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            $this->posted[] = [$method, $url, \is_string($body) ? $body : ''];

            return new MockResponse('', ['http_code' => 204]);
        });
    }
}
