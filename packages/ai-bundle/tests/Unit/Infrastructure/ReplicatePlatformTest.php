<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\AiBundle\Infrastructure\Replicate\ReplicateImageClient;
use Phil\AiBundle\Infrastructure\Replicate\ReplicateImageConverter;
use Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ReplicatePlatformFactory::class)]
#[CoversClass(ReplicateImageClient::class)]
#[CoversClass(ReplicateImageConverter::class)]
final class ReplicatePlatformTest extends TestCase
{
    public function testAPredictionIsCreatedAndItsImageFetched(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return 1 === \count($requests)
                ? new JsonMockResponse(['status' => 'succeeded', 'output' => ['https://replicate.delivery/xezq/out-0.webp', 'https://replicate.delivery/xezq/out-1.webp']])
                : new MockResponse('WEBP', ['response_headers' => ['content-type' => 'image/webp']]);
        });

        $result = new ReplicatePlatformFactory($http, wait: 30)->create('r8-member')->invoke('black-forest-labs/flux-schnell', 'A red dragon', ['aspect_ratio' => '16:9', 'stream' => true])->getResult();

        self::assertInstanceOf(BinaryResult::class, $result);
        self::assertSame(['WEBP', 'image/webp'], [$result->getContent(), $result->getMimeType()]);
        self::assertSame(['POST', 'https://api.replicate.com/v1/models/black-forest-labs/flux-schnell/predictions'], [$requests[0][0], $requests[0][1]]);
        self::assertSame('{"input":{"prompt":"A red dragon","aspect_ratio":"16:9"}}', $requests[0][2]['body']);
        $headers = $requests[0][2]['headers'] ?? null;
        self::assertIsArray($headers);
        self::assertContains('Authorization: Bearer r8-member', $headers);
        self::assertContains('Prefer: wait=30', $headers);
        self::assertSame(35.0, $requests[0][2]['timeout']);
        self::assertSame(['GET', 'https://replicate.delivery/xezq/out-0.webp', 0], [$requests[1][0], $requests[1][1], $requests[1][2]['max_redirects']]);
    }

    public function testASingleOutputAndAMissingTypeAreRead(): void
    {
        $converter = new ReplicateImageConverter(new MockHttpClient([new MockResponse('PNG')]));

        $result = $converter->convert(new InMemoryRawResult(['status' => 'succeeded', 'output' => 'https://cdn.replicate.delivery/a.png']));

        self::assertInstanceOf(BinaryResult::class, $result);
        self::assertSame(['PNG', 'application/octet-stream'], [$result->getContent(), $result->getMimeType()]);
        self::assertTrue($converter->supports(new Model('any')));
        self::assertNull($converter->getTokenUsageExtractor());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unusablePredictions(): iterable
    {
        yield 'failed' => [['status' => 'failed', 'error' => 'NSFW'], 'The Replicate prediction did not succeed: failed.'];
        yield 'still running' => [['status' => 'processing'], 'The Replicate prediction did not succeed: processing.'];
        yield 'no status' => [[], 'The Replicate prediction did not succeed: no status.'];
        yield 'no output' => [['status' => 'succeeded', 'output' => []], 'The Replicate prediction has no image address.'];
        yield 'not an address' => [['status' => 'succeeded', 'output' => 'not a url'], 'The Replicate prediction gave no valid image address.'];
        yield 'another host' => [['status' => 'succeeded', 'output' => 'https://example.com/a.png'], 'An image is fetched from replicate.delivery only, over HTTPS.'];
        yield 'a look-alike host' => [['status' => 'succeeded', 'output' => 'https://evilreplicate.delivery/a.png'], 'An image is fetched from replicate.delivery only, over HTTPS.'];
        yield 'plain HTTP' => [['status' => 'succeeded', 'output' => 'http://replicate.delivery/a.png'], 'An image is fetched from replicate.delivery only, over HTTPS.'];
    }

    /**
     * @param array<string, mixed> $prediction
     */
    #[DataProvider('unusablePredictions')]
    public function testAnUnusablePredictionIsRefusedWithoutFetchingAnything(array $prediction, string $message): void
    {
        $http = new MockHttpClient(static fn (): never => throw new LogicException('Nothing is fetched.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ReplicateImageConverter($http)->convert(new InMemoryRawResult($prediction));
    }

    public function testAnImageTooLargeOrUnavailableIsRefused(): void
    {
        $prediction = new InMemoryRawResult(['status' => 'succeeded', 'output' => 'https://replicate.delivery/a.png']);

        try {
            new ReplicateImageConverter(new MockHttpClient([new MockResponse(['ab', 'cd'])]), 3)->convert($prediction);
            self::fail('An image past the bound was read.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The image is larger than 3 bytes.', $runtimeException->getMessage());
        }

        self::assertSame('abc', $this->content(new ReplicateImageConverter(new MockHttpClient([new MockResponse('abc')]), 3)->convert($prediction)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The image could not be fetched: HTTP 404.');
        new ReplicateImageConverter(new MockHttpClient([new MockResponse('', ['http_code' => 404])]))->convert($prediction);
    }

    public function testTheClientTakesAModelNameAndAPromptAsText(): void
    {
        $client = new ReplicateImageClient(new MockHttpClient(), 'r8');

        self::assertTrue($client->supports(new Model('stability-ai/sdxl')));
        self::assertTrue($client->supports(new Model('a/b.c-d_e')));
        self::assertFalse($client->supports(new Model('sdxl')));
        self::assertFalse($client->supports(new Model('../v1/x')));
        self::assertFalse($client->supports(new Model('Owner/model')));
        self::assertFalse($client->supports(new Model("a/b\n")));
        self::assertFalse($client->supports(new Model('a/b/c')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A Replicate image model takes the prompt as text.');
        $client->request(new Model('a/b'), ['messages' => []]);
    }

    public function testTheWaitIsBounded(): void
    {
        self::assertInstanceOf(ReplicateImageClient::class, new ReplicateImageClient(new MockHttpClient(), 'r8', 1));
        self::assertInstanceOf(ReplicateImageClient::class, new ReplicateImageClient(new MockHttpClient(), 'r8', 60));

        foreach ([0, 61] as $wait) {
            try {
                new ReplicateImageClient(new MockHttpClient(), 'r8', $wait);
                self::fail((string) $wait);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('Replicate waits 1 to 60 seconds for a prediction; got %d.', $wait), $invalidArgumentException->getMessage());
            }
        }
    }

    private function content(mixed $result): string
    {
        self::assertInstanceOf(BinaryResult::class, $result);

        return $result->getContent();
    }
}
