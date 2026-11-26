<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Functional;

use Override;
use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use Phil\CorsBundle\Infrastructure\EventListener\CorsListener;
use Phil\CorsBundle\Infrastructure\PathPolicyResolver;
use Phil\CorsBundle\PhilCorsBundle;
use Phil\CorsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PhilCorsBundle::class)]
#[CoversClass(CorsListener::class)]
#[CoversClass(CorsHeaders::class)]
#[CoversClass(CorsPolicy::class)]
#[CoversClass(PathRule::class)]
#[CoversClass(PathPolicyResolver::class)]
final class CorsBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testAPreflightIsAnsweredBeforeTheRouter(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/api/books', server: [
            'HTTP_ORIGIN' => 'https://app.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('https://app.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('GET, PUT', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        self::assertSame('3600', $response->headers->get('Access-Control-Max-Age'));
        self::assertFalse($response->headers->has('Access-Control-Expose-Headers'));
    }

    public function testAResponseToTheAllowedOrigin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/books', server: ['HTTP_ORIGIN' => 'https://app.example.com']);

        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://app.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('ETag', $response->headers->get('Access-Control-Expose-Headers'));
        self::assertSame(['Origin'], $response->getVary());
    }

    public function testAnotherOriginLosesTheControllersWildcard(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/books', server: ['HTTP_ORIGIN' => 'https://evil.example']);

        $response = $client->getResponse();

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        self::assertSame(['Origin'], $response->getVary());
    }

    public function testAnErrorPageOfAConfiguredPathGetsTheHeaders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/missing', server: ['HTTP_ORIGIN' => 'https://app.example.com']);

        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('https://app.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testThePublicFeedAnswersAnyOrigin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/feed', server: ['HTTP_ORIGIN' => 'https://anyone.example']);

        $response = $client->getResponse();

        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function testAPageOutsideThePathsIsLeftAlone(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/page', server: ['HTTP_ORIGIN' => 'https://app.example.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);

        self::assertSame(405, $client->getResponse()->getStatusCode());

        $client->request('GET', '/page', server: ['HTTP_ORIGIN' => 'https://app.example.com']);

        self::assertSame('*', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        self::assertSame([], $client->getResponse()->getVary());
    }
}
