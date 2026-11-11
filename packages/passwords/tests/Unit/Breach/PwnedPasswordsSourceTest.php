<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Breach;

use Phil\Passwords\Breach\Adapter\PwnedPasswordsSource;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(PwnedPasswordsSource::class)]
#[CoversClass(BreachCheckUnavailable::class)]
final class PwnedPasswordsSourceTest extends TestCase
{
    private const string BODY = "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n1E4C9B93F3F0682250B6CF8331B7EE68FD8:10434004\r\n";

    public function testOnlyThePrefixIsSentWithPaddingAndTheUserAgent(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(self::BODY);
        });

        self::assertSame(10434004, new PwnedPasswordsSource($client)->occurrences('password'));

        [[$method, $url, $options]] = $requests;
        self::assertIsArray($options['headers']);
        self::assertSame('GET', $method);
        self::assertSame('https://api.pwnedpasswords.com/range/5BAA6', $url);
        self::assertContains('User-Agent: phil-passwords', $options['headers']);
        self::assertContains('Add-Padding: true', $options['headers']);
        self::assertSame(5.0, $options['timeout']);
        self::assertSame(10.0, $options['max_duration']);
    }

    public function testAMirrorWithoutPaddingAndItsOwnSettings(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, $options];

            return new MockResponse('');
        });

        self::assertSame(0, new PwnedPasswordsSource($client, 'https://mirror.example/range', false, 2.0, 'my-site')->occurrences('password'));

        [[$url, $options]] = $requests;
        self::assertIsArray($options['headers']);
        self::assertSame('https://mirror.example/range/5BAA6', $url);
        self::assertContains('User-Agent: my-site', $options['headers']);
        self::assertNotContains('Add-Padding: true', $options['headers']);
        self::assertSame(2.0, $options['timeout']);
        self::assertSame(4.0, $options['max_duration']);
    }

    public function testAStatusOtherThan200(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse('', ['http_code' => 503])));

        $this->expectExceptionObject(new BreachCheckUnavailable('The breach source answered with the HTTP status 503.'));

        $source->occurrences('password');
    }

    public function testARedirectionIsNotAnAnswer(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse('', ['http_code' => 304])));

        $this->expectExceptionObject(new BreachCheckUnavailable('The breach source answered with the HTTP status 304.'));

        $source->occurrences('password');
    }

    public function testATransportFailure(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse([new TransportException('timeout')])));

        try {
            $source->occurrences('password');
            self::fail('No failure.');
        } catch (BreachCheckUnavailable $breachCheckUnavailable) {
            self::assertSame('The breach source could not be reached.', $breachCheckUnavailable->getMessage());
            self::assertInstanceOf(TransportException::class, $breachCheckUnavailable->getPrevious());
            self::assertSame(0, $breachCheckUnavailable->getCode());
        }
    }

    public function testATooLargeAnswer(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse(str_repeat(self::BODY, 4))), maxBytes: 100);

        $this->expectExceptionObject(new BreachCheckUnavailable('The breach source answered with more than 100 bytes.'));

        $source->occurrences('password');
    }

    public function testAnAnswerAtTheBound(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse(self::BODY)), maxBytes: \strlen(self::BODY));

        self::assertSame(10434004, $source->occurrences('password'));
    }

    public function testAMalformedAnswer(): void
    {
        $source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse('<html>')));

        $this->expectExceptionObject(BreachCheckUnavailable::malformed());

        $source->occurrences('password');
    }
}
