<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Plays Gotenberg for the application's HTTP client: a PDF for any request.
 */
final class GotenbergResponses
{
    public function __invoke(): ResponseInterface
    {
        return new MockResponse('%PDF-gotenberg', ['response_headers' => ['Content-Type' => 'application/pdf']]);
    }
}
