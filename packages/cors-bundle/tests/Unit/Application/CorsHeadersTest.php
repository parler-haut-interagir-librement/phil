<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Unit\Application;

use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Domain\CorsPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CorsHeaders::class)]
final class CorsHeadersTest extends TestCase
{
    private const array ALLOW = [
        'access-control-allow-origin',
        'access-control-allow-credentials',
        'access-control-allow-methods',
        'access-control-allow-headers',
        'access-control-max-age',
        'access-control-allow-private-network',
        'access-control-expose-headers',
    ];

    public function testAPreflightIsAnOptionsRequestWithAnOriginAndAMethod(): void
    {
        $headers = new CorsHeaders();

        self::assertTrue($headers->isPreflight($this->preflight('https://a.test', 'PUT')));
        self::assertFalse($headers->isPreflight(Request::create('/api', 'OPTIONS', server: ['HTTP_ORIGIN' => 'https://a.test'])));
        self::assertFalse($headers->isPreflight(Request::create('/api', 'OPTIONS', server: ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT'])));
        self::assertFalse($headers->isPreflight(Request::create('/api', 'GET', server: ['HTTP_ORIGIN' => 'https://a.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT'])));
    }

    public function testAnAllowedPreflight(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowMethods: ['GET', 'PUT'], allowHeaders: ['Content-Type', 'Authorization'], exposeHeaders: ['ETag'], allowCredentials: true, maxAge: 600);
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'put', 'content-type, Authorization'), $policy);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame('https://a.test', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('GET, PUT', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, Authorization', $response->headers->get('Access-Control-Allow-Headers'));
        self::assertSame('600', $response->headers->get('Access-Control-Max-Age'));
        self::assertFalse($response->headers->has('Access-Control-Expose-Headers'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Private-Network'));
        self::assertSame(['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers'], $response->getVary());
    }

    public function testAPreflightWithoutHeadersOrMaxAge(): void
    {
        $response = new CorsHeaders()->preflight($this->preflight('https://b.test', 'POST'), new CorsPolicy(['*']));

        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
        self::assertSame('GET, HEAD, POST', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Headers'));
        self::assertFalse($response->headers->has('Access-Control-Max-Age'));
    }

    public function testAZeroMaxAgeIsSent(): void
    {
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'GET'), new CorsPolicy(['https://a.test'], maxAge: 0));

        self::assertSame('0', $response->headers->get('Access-Control-Max-Age'));
    }

    public function testAnyHeaderEchoesTheRequestedOnes(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowHeaders: ['*']);
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'POST', " Authorization ,\tX-Trace,,x-trace,"), $policy);

        self::assertSame('authorization, x-trace', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testAnyHeaderWithoutRequestedHeadersSendsNone(): void
    {
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'POST'), new CorsPolicy(['https://a.test'], allowHeaders: ['*']));

        self::assertFalse($response->headers->has('Access-Control-Allow-Headers'));
        self::assertSame('https://a.test', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testARefusedOriginGetsNoAllowHeader(): void
    {
        $this->assertRefused($this->preflight('https://evil.test', 'GET'), new CorsPolicy(['https://a.test']));
    }

    public function testARefusedMethodGetsNoAllowHeader(): void
    {
        $this->assertRefused($this->preflight('https://a.test', 'DELETE'), new CorsPolicy(['https://a.test']));
    }

    public function testARefusedHeaderGetsNoAllowHeader(): void
    {
        $this->assertRefused($this->preflight('https://a.test', 'GET', 'Content-Type, X-Secret'), new CorsPolicy(['https://a.test'], allowHeaders: ['Content-Type']));
    }

    public function testRequestedHeadersWithinTheBoundsAreRead(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowHeaders: ['*']);
        $names = array_map(static fn (int $i): string => 'x-'.$i, range(1, CorsHeaders::REQUEST_HEADERS_MAX_COUNT));
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'GET', implode(',', $names)), $policy);

        self::assertSame(implode(', ', $names), $response->headers->get('Access-Control-Allow-Headers'));

        $long = str_pad('x-a', CorsHeaders::REQUEST_HEADERS_MAX_BYTES, 'a');
        $response = new CorsHeaders()->preflight($this->preflight('https://a.test', 'GET', $long), $policy);

        self::assertSame($long, $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testTooManyRequestedHeadersAreRefused(): void
    {
        $names = array_map(static fn (int $i): string => 'x-'.$i, range(0, CorsHeaders::REQUEST_HEADERS_MAX_COUNT));

        $this->assertRefused($this->preflight('https://a.test', 'GET', implode(',', $names)), new CorsPolicy(['https://a.test'], allowHeaders: ['*']));
    }

    public function testARequestedHeadersValueTooLongIsRefused(): void
    {
        $long = str_pad('x-a', CorsHeaders::REQUEST_HEADERS_MAX_BYTES + 1, 'a');

        $this->assertRefused($this->preflight('https://a.test', 'GET', $long), new CorsPolicy(['https://a.test'], allowHeaders: ['*']));
    }

    public function testThePrivateNetworkIsAllowedWhenAskedAndConfigured(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowPrivateNetwork: true);
        $request = $this->preflight('https://a.test', 'GET');
        $request->headers->set('Access-Control-Request-Private-Network', 'true');

        $response = new CorsHeaders()->preflight($request, $policy);

        self::assertSame('true', $response->headers->get('Access-Control-Allow-Private-Network'));
        self::assertSame(['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers', 'Access-Control-Request-Private-Network'], $response->getVary());

        $unasked = new CorsHeaders()->preflight($this->preflight('https://a.test', 'GET'), $policy);

        self::assertFalse($unasked->headers->has('Access-Control-Allow-Private-Network'));
    }

    public function testThePrivateNetworkIsRefusedWhenNotConfigured(): void
    {
        $request = $this->preflight('https://a.test', 'GET');
        $request->headers->set('Access-Control-Request-Private-Network', 'true');

        $response = new CorsHeaders()->preflight($request, new CorsPolicy(['https://a.test']));

        self::assertFalse($response->headers->has('Access-Control-Allow-Private-Network'));
        self::assertSame('https://a.test', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAResponseToAnAllowedOrigin(): void
    {
        $policy = new CorsPolicy(['https://a.test'], exposeHeaders: ['ETag', 'Link'], allowCredentials: true);
        $response = new Response('{}');
        $response->setVary('Accept-Language');
        new CorsHeaders()->apply(Request::create('/api', server: ['HTTP_ORIGIN' => 'https://a.test']), $response, $policy);

        self::assertSame('https://a.test', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('ETag, Link', $response->headers->get('Access-Control-Expose-Headers'));
        self::assertSame(['Accept-Language', 'Origin'], $response->getVary());
        self::assertFalse($response->headers->has('Access-Control-Allow-Methods'));
    }

    public function testAResponseWithoutExposedHeaders(): void
    {
        $response = new Response();
        new CorsHeaders()->apply(Request::create('/api', server: ['HTTP_ORIGIN' => 'https://a.test']), $response, new CorsPolicy(['*']));

        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
        self::assertFalse($response->headers->has('Access-Control-Expose-Headers'));
        self::assertSame(['Origin'], $response->getVary());
    }

    public function testAResponseToARefusedOriginOrToNoOriginLosesTheApplicationsHeaders(): void
    {
        foreach ([['HTTP_ORIGIN' => 'https://evil.test'], []] as $server) {
            $response = new Response();
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Secret');
            new CorsHeaders()->apply(Request::create('/api', server: $server), $response, new CorsPolicy(['https://a.test']));

            foreach (self::ALLOW as $header) {
                self::assertFalse($response->headers->has($header), $header);
            }

            self::assertSame(['Origin'], $response->getVary());
        }
    }

    private function assertRefused(Request $request, CorsPolicy $policy): void
    {
        $response = new CorsHeaders()->preflight($request, $policy);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        foreach (self::ALLOW as $header) {
            self::assertFalse($response->headers->has($header), $header);
        }

        self::assertContains('Origin', $response->getVary());
    }

    public function testAPreflightWithoutAnOriginIsRefusedWithoutHeaders(): void
    {
        $request = Request::create('/api', 'OPTIONS', server: ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);
        $response = new CorsHeaders()->preflight($request, new CorsPolicy(['*']));

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testTheRequestedHeadersAreAListOfLowercaseNames(): void
    {
        $method = new ReflectionMethod(CorsHeaders::class, 'requestedHeaders');

        self::assertSame(['x-a', 'x-b'], $method->invoke(new CorsHeaders(), 'X-A, x-b, X-A'));
    }

    private function preflight(string $origin, string $method, ?string $headers = null): Request
    {
        $server = ['HTTP_ORIGIN' => $origin, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method];

        if (null !== $headers) {
            $server['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = $headers;
        }

        return Request::create('/api', 'OPTIONS', server: $server);
    }
}
