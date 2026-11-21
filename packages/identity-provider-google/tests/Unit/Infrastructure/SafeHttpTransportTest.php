<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\IdentityProviderGoogle\Infrastructure\Http\SafeHttpResponse;
use Phil\IdentityProviderGoogle\Infrastructure\Http\SafeHttpTransport;
use Phil\IdentityProviderGoogle\Tests\Fixture\FakeGoogle;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(SafeHttpTransport::class)]
final class SafeHttpTransportTest extends TestCase
{
    public function testTheLimitsOfAProvider(): void
    {
        $limits = SafeHttpTransport::limits();

        self::assertSame(5.0, $limits->timeout);
        self::assertSame(10.0, $limits->maxDuration);
        self::assertSame(1_048_576, $limits->maxBytes);
        self::assertSame(0, $limits->maxRedirects);
        self::assertSame('PhilIdentityProviderGoogle/0.1', $limits->userAgent);
        self::assertSame('Example/1.0', SafeHttpTransport::limits('Example/1.0')->userAgent);
    }

    public function testAFormIsPostedWithItsCredentials(): void
    {
        $google = new FakeGoogle();
        $response = $google->transport()->request('POST', FakeGoogle::TOKEN, [
            'body' => ['grant_type' => 'authorization_code', 'code' => 'a b'],
            'auth_basic' => 'id:secret',
            'headers' => ['X-One' => ' one ', 'X-Two: two', ' X-Three : three'],
            'max_redirects' => 0,
        ]);

        self::assertInstanceOf(SafeHttpResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('POST', $google->requests[0]['method']);
        self::assertSame('grant_type=authorization_code&code=a+b', $google->requests[0]['body']);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', $google->requests[0]['headers']);
        self::assertContains('Authorization: Basic aWQ6c2VjcmV0', $google->requests[0]['headers']);
        self::assertContains('X-One: one', $google->requests[0]['headers']);
        self::assertContains('X-Two: two', $google->requests[0]['headers']);
        self::assertContains('X-Three: three', $google->requests[0]['headers']);
        self::assertContains('Accept: application/json, application/jwk-set+json;q=0.9, */*;q=0.1', $google->requests[0]['headers']);
        self::assertContains('User-Agent: PhilIdentityProviderGoogle/0.1', $google->requests[0]['headers']);
    }

    public function testABearerTokenAndAStringBody(): void
    {
        $google = new FakeGoogle();
        $google->transport()->request('POST', FakeGoogle::TOKEN, ['auth_bearer' => 'token', 'body' => 'raw']);

        self::assertSame('raw', $google->requests[0]['body']);
        self::assertContains('Authorization: Bearer token', $google->requests[0]['headers']);
        self::assertSame([], array_filter($google->requests[0]['headers'], static fn (string $header): bool => str_starts_with($header, 'Content-Type')));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedOptions(): iterable
    {
        yield 'an unknown option' => [['proxy' => 'x', 'verify_peer' => false], 'The option "proxy", "verify_peer" is not supported.'];
        yield 'a body that is a number' => [['body' => 1], 'The body is a string or a form.'];
        yield 'credentials as a list' => [['auth_basic' => ['id', 'secret']], 'A credential is a string.'];
        yield 'empty credentials' => [['auth_bearer' => ''], 'A credential is a string.'];
        yield 'headers that are a string' => [['headers' => 'X: y'], 'The headers are a list.'];
        yield 'a header without a name' => [['headers' => ['no colon']], 'A header is a name and a string.'];
        yield 'a header that is a number' => [['headers' => ['X' => 1]], 'A header is a name and a string.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refusedOptions')]
    public function testAnOptionItWouldDropIsRefused(array $options, string $message): void
    {
        $google = new FakeGoogle();

        try {
            $google->transport()->request('POST', FakeGoogle::TOKEN, $options);
            self::fail('The request was sent.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame($message, $invalidArgumentException->getMessage());
        }

        self::assertSame([], $google->requests);
    }

    public function testARefusalOfTheGuardIsATransportError(): void
    {
        $transport = new SafeHttpTransport(new SafeHttpClient(new MockHttpClient([new MockResponse('')]), SafeHttpTransport::limits(), ['internal.example' => '10.0.0.1']));

        try {
            $transport->request('GET', 'https://internal.example/');
            self::fail('The request was sent.');
        } catch (TransportException $transportException) {
            self::assertSame('"https://internal.example/" is or resolves to a private, loopback, link-local or reserved network.', $transportException->getMessage());
            self::assertNotNull($transportException->getPrevious());
        }
    }

    public function testARedirectIsNotFollowed(): void
    {
        $google = new FakeGoogle();
        $google->answers[FakeGoogle::DISCOVERY] = new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://accounts.google.com/elsewhere']]);

        $this->expectException(TransportException::class);
        $google->transport()->request('GET', FakeGoogle::DISCOVERY);
    }

    public function testTheAnswersAreStreamedInOneChunk(): void
    {
        $transport = new FakeGoogle()->transport();
        $first = $transport->request('GET', FakeGoogle::DISCOVERY);
        $second = $transport->request('GET', FakeGoogle::JWKS);
        $chunks = [];

        foreach ($transport->stream([$first, $second]) as $response => $chunk) {
            $chunks[] = [$response, $chunk->isFirst(), $chunk->isLast(), $chunk->getContent(), $chunk->getOffset()];
        }

        $discovery = FakeGoogle::fixture('google-openid-configuration.json');
        self::assertCount(6, $chunks);
        self::assertSame([$first, true, false, '', 0], $chunks[0]);
        self::assertSame([$first, false, false, $discovery, 0], $chunks[1]);
        self::assertSame([$first, false, true, '', \strlen($discovery)], $chunks[2]);
        self::assertSame($second, $chunks[3][0]);

        $single = iterator_to_array($transport->stream($first), false);
        self::assertCount(3, $single);
    }

    public function testAnAnswerOfAnotherClientIsNotStreamed(): void
    {
        $other = self::createStub(ResponseInterface::class);

        $this->expectExceptionObject(new InvalidArgumentException('Only the answers of this client are streamed.'));
        iterator_to_array(new FakeGoogle()->transport()->stream($other));
    }

    public function testTheErrorStatusesAreStreamedToo(): void
    {
        $google = new FakeGoogle();
        $response = $google->transport()->request('GET', 'https://accounts.google.com/missing');
        $content = '';

        foreach ($google->transport()->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }

        self::assertSame('{"error":"not_found"}', $content);
    }

    public function testOptionsAreNotSetAhead(): void
    {
        $this->expectExceptionObject(new LogicException('The options of this client come with each request.'));

        new FakeGoogle()->transport()->withOptions([]);
    }

    public function testResetForgetsWhatTheGuardResolved(): void
    {
        $inner = new class extends MockHttpClient implements ResetInterface {
            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
            }
        };

        new SafeHttpTransport(new SafeHttpClient($inner))->reset();

        self::assertSame(1, $inner->resets);
    }

    public function testAHeaderKeepsTheColonsOfItsValue(): void
    {
        $google = new FakeGoogle();
        $google->transport()->request('GET', FakeGoogle::TOKEN, ['headers' => ['X-A' => 'b:c', 'X-B: d:e:f', 'X-C' => 'g']]);

        self::assertContains('X-A: b:c', $google->requests[0]['headers']);
        self::assertContains('X-B: d:e:f', $google->requests[0]['headers']);
        self::assertContains('X-C: g', $google->requests[0]['headers']);
    }
}
