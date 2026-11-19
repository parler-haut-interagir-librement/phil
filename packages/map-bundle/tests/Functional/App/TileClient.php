<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The tile source of the application: every tile is the same PNG.
 */
final class TileClient
{
    public static function create(): MockHttpClient
    {
        return new MockHttpClient(static fn (): MockResponse => new MockResponse('PNG', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']]));
    }
}
