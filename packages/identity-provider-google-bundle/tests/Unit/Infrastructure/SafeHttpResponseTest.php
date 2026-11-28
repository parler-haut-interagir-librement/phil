<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderGoogleBundle\Infrastructure\Http\SafeHttpResponse;
use Phil\SafeHttp\SafeResponse;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Throwable;

#[CoversClass(SafeHttpResponse::class)]
final class SafeHttpResponseTest extends TestCase
{
    public function testAnAnswerAsSymfonyGivesIt(): void
    {
        $response = $this->response(200, '{"a":1,"big":123456789012345678901234567890}', ['content-type' => ['application/json'], 'x' => ['1', '2']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['content-type' => ['application/json'], 'x' => ['1', '2']], $response->getHeaders());
        self::assertSame(['a' => 1, 'big' => '123456789012345678901234567890'], $response->toArray());
        $response->cancel();
        self::assertSame([
            'http_code' => 200,
            'http_method' => 'GET',
            'url' => 'https://example.com/a',
            'response_headers' => ['HTTP/1.1 200', 'content-type: application/json', 'x: 1', 'x: 2'],
            'redirect_count' => 1,
            'canceled' => false,
            'error' => null,
        ], $response->getInfo());
        self::assertSame('https://example.com/a', $response->getInfo('url'));
        self::assertNull($response->getInfo('unknown'));
    }

    /**
     * @return iterable<string, array{int, class-string}>
     */
    public static function errors(): iterable
    {
        yield 'a redirect' => [302, RedirectionException::class];
        yield 'the first redirect status' => [300, RedirectionException::class];
        yield 'the last redirect status' => [399, RedirectionException::class];
        yield 'a client error' => [400, ClientException::class];
        yield 'the last client error' => [499, ClientException::class];
        yield 'a server error' => [500, ServerException::class];
    }

    /**
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('errors')]
    public function testAnErrorStatusThrowsUnlessAskedNotTo(int $status, string $exception): void
    {
        $response = $this->response($status, '{"error":"x"}');

        self::assertSame('{"error":"x"}', $response->getContent(false));
        self::assertSame([], $response->getHeaders(false));
        self::assertSame(['error' => 'x'], $response->toArray(false));

        $this->expectException($exception);
        $response->getContent();
    }

    public function testTheLastSuccessIsNoError(): void
    {
        self::assertSame('ok', $this->response(299, 'ok')->getContent());
    }

    public function testTheHeadersThrowOnAnError(): void
    {
        $this->expectException(ClientException::class);

        $this->response(404, '')->getHeaders();
    }

    public function testABodyThatIsNotJson(): void
    {
        try {
            $this->response(200, 'nope')->toArray();
            self::fail('The body was decoded.');
        } catch (JsonException $jsonException) {
            // PHP 8.6 adds the location of the error to the message of json_decode().
            self::assertStringStartsWith('The answer of "https://example.com/a" is not JSON: Syntax error', $jsonException->getMessage());
            self::assertStringEndsWith('.', $jsonException->getMessage());
            self::assertNotNull($jsonException->getPrevious());
        }
    }

    public function testJsonThatIsNotAnObject(): void
    {
        $this->expectExceptionObject(new JsonException('The answer of "https://example.com/a" is not a JSON object.'));

        $this->response(200, '"text"')->toArray();
    }

    public function testJsonAsDeepAsAllowed(): void
    {
        self::assertCount(1, self::response(200, str_repeat('[', 511).'1'.str_repeat(']', 511))->toArray());
    }

    public function testJsonDeeperThanAllowed(): void
    {
        $this->expectException(JsonException::class);

        $this->response(200, str_repeat('[', 513).str_repeat(']', 513))->toArray();
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function response(int $status, string $body, array $headers = []): SafeHttpResponse
    {
        $url = Url::fromString('https://example.com/a');

        return new SafeHttpResponse('GET', new SafeResponse($url, $status, $headers, $body, true, [$url]));
    }

    public function testTheContentOfAnErrorIsNotDecodedUnlessAskedNotTo(): void
    {
        $response = $this->response(500, '{"error":"x"}');

        self::assertSame(['error' => 'x'], $response->toArray(false));
        $this->expectException(ServerException::class);
        $response->toArray();
    }
}
