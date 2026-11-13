<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Tests\Unit;

use Phil\SvgBlocks\Exception\InvalidConfiguration;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgBlocks\Http\HttpMermaidService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpMermaidService::class)]
#[CoversClass(RenderingFailed::class)]
#[CoversClass(InvalidConfiguration::class)]
final class HttpMermaidServiceTest extends TestCase
{
    public function testPostsTheCodeAndReturnsTheImage(): void
    {
        $sent = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = [$method, $url, $options['body'] ?? null, $options['timeout'] ?? null, $options['max_duration'] ?? null, $options['max_redirects'] ?? null, $options['headers'] ?? []];

            return new MockResponse('<svg/>', ['http_code' => 200, 'response_headers' => ['content-type' => 'image/svg+xml; charset=utf-8']]);
        });

        self::assertSame('<svg/>', new HttpMermaidService($client, 'http://mermaid:3000/', 2.5)->toSvg('graph TD'));
        self::assertSame(['POST', 'http://mermaid:3000/render', '{"code":"graph TD"}', 2.5, 2.5, 0], \array_slice($sent, 0, 6));
        self::assertIsArray($sent[6]);
        self::assertContains('Accept: image/svg+xml', $sent[6]);
    }

    public function testDefaultsToTenSeconds(): void
    {
        $timeout = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeout): MockResponse {
            $timeout = $options['timeout'] ?? null;

            return new MockResponse('<svg/>', ['response_headers' => ['content-type' => 'image/svg+xml']]);
        });
        new HttpMermaidService($client, 'https://diagrams.example/base')->toSvg('x');

        self::assertSame(10.0, $timeout);
    }

    /**
     * @return iterable<string, array{MockResponse, string}>
     */
    public static function failures(): iterable
    {
        yield 'refused diagram' => [new MockResponse('{"error":"Parse error on line 1"}', ['http_code' => 422]), 'Mermaid refuses the diagram: Parse error on line 1.'];
        yield 'refused without reason' => [new MockResponse('nope', ['http_code' => 422]), 'Mermaid refuses the diagram: no reason given.'];
        yield 'refused with a reason not a text' => [new MockResponse('{"error":1}', ['http_code' => 422]), 'Mermaid refuses the diagram: no reason given.'];
        yield 'server error' => [new MockResponse('oops', ['http_code' => 500, 'response_headers' => ['content-type' => 'text/plain']]), 'The diagram service answered 500 text/plain instead of an SVG image.'];
        yield 'not an image' => [new MockResponse('<html/>', ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']]), 'answered 200 text/html instead of an SVG image.'];
        yield 'without a type' => [new MockResponse('<svg/>', ['http_code' => 200]), 'answered 200 without a type instead of an SVG image.'];
        yield 'too large' => [new MockResponse(str_repeat('x', 101), ['response_headers' => ['content-type' => 'image/svg+xml']]), 'The diagram is larger than 100 bytes.'];
        yield 'unreachable' => [new MockResponse([new TransportException('Connection refused')]), 'The diagram service did not answer: Connection refused'];
    }

    #[DataProvider('failures')]
    public function testFailsWithAReasonForTheAuthor(MockResponse $response, string $message): void
    {
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains($message);

        new HttpMermaidService(new MockHttpClient($response), 'http://mermaid:3000', 1.0, 100)->toSvg('graph');
    }

    public function testCutsALongReason(): void
    {
        try {
            new HttpMermaidService(new MockHttpClient(new MockResponse(json_encode(['error' => str_repeat('é', 600)], \JSON_THROW_ON_ERROR), ['http_code' => 422])), 'http://m')->toSvg('x');
            self::fail('Refused.');
        } catch (RenderingFailed $renderingFailed) {
            self::assertSame('Mermaid refuses the diagram: '.str_repeat('é', 500).'.', $renderingFailed->getMessage());
        }
    }

    public function testReadsAMebibyteByDefault(): void
    {
        $exact = str_repeat('x', 1_048_576);
        self::assertSame($exact, new HttpMermaidService(new MockHttpClient(new MockResponse($exact, ['response_headers' => ['content-type' => 'image/svg+xml']])), 'http://m')->toSvg('x'));

        $this->expectExceptionMessageIsOrContains('The diagram is larger than 1048576 bytes.');
        new HttpMermaidService(new MockHttpClient(new MockResponse($exact.'x', ['response_headers' => ['content-type' => 'image/svg+xml']])), 'http://m')->toSvg('x');
    }

    public function testReadsAnAnswerOfExactlyTheLimit(): void
    {
        self::assertSame('0', new HttpMermaidService(new MockHttpClient(new MockResponse('0', ['response_headers' => ['content-type' => 'image/svg+xml']])), 'http://m', 1.0, 1)->toSvg('x'));
        self::assertSame('0123456789', new HttpMermaidService(new MockHttpClient(new MockResponse('0123456789', ['response_headers' => ['content-type' => 'image/svg+xml']])), 'http://m', 1.0, 10)->toSvg('x'));
    }

    /**
     * @return iterable<array{string, float, int}>
     */
    public static function invalid(): iterable
    {
        yield ['ftp://mermaid', 1.0, 1];
        yield ['http://mermaid?x=1', 1.0, 1];
        yield ['mermaid:3000', 1.0, 1];
        yield ["http://mermaid\n", 1.0, 1];
        yield ['http://mermaid', 0.0, 1];
        yield ['http://mermaid', 1.0, 0];
    }

    #[DataProvider('invalid')]
    public function testRefusesAnInvalidConfiguration(string $endpoint, float $timeout, int $maxBytes): void
    {
        $this->expectException(InvalidConfiguration::class);

        new HttpMermaidService(new MockHttpClient(), $endpoint, $timeout, $maxBytes);
    }
}
