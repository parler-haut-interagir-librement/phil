<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\ServiceUnavailable;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\RequestOptions;
use Phil\PagedMedia\Gotenberg\Result;
use Phil\PagedMedia\Gotenberg\Stage\Merge;
use Phil\PagedMedia\Gotenberg\Webhook;
use Phil\PagedMedia\Tests\Unit\Fixture\GotenbergServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    private GotenbergServer $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new GotenbergServer();
    }

    #[DataProvider('dispositions')]
    public function testTheNameComesFromTheContentDisposition(string $disposition, ?string $name): void
    {
        self::assertSame($name, $this->answered(['Content-Disposition' => $disposition])->filename);
    }

    /**
     * @return iterable<array{string, ?string}>
     */
    public static function dispositions(): iterable
    {
        yield ['attachment; filename="report.pdf"', 'report.pdf'];
        yield ['attachment; filename=report.pdf', 'report.pdf'];
        yield ['attachment; FILENAME="a b.pdf"', 'a b.pdf'];
        yield ['attachment; filename="facture-ete.pdf"; filename*=UTF-8\'\'facture-%C3%A9t%C3%A9.pdf', 'facture-été.pdf'];
        yield ['attachment; filename*=utf-8\'\'r%C3%A9sum%C3%A9.zip; filename="resume.zip"', 'résumé.zip'];
        yield ['attachment; filename*=iso-8859-1\'\'a.pdf; filename="b.pdf"', 'b.pdf'];
        yield ['attachment; filename="a=b.pdf"', 'a=b.pdf'];
        yield ["attachment; FILENAME*=UTF-8''%C3%A9.pdf", 'é.pdf'];
        yield ['attachment', null];
        yield ['', null];
    }

    public function testTheRemediationCountsAreRead(): void
    {
        $result = $this->answered(['Gotenberg-Remediation-Residual' => '3', 'Gotenberg-Remediation-Rolled-Back' => '0']);

        self::assertSame(3, $result->residual);
        self::assertSame(0, $result->rolledBack);
        self::assertFalse($result->conforms());
        self::assertTrue($this->answered(['Gotenberg-Remediation-Residual' => '0'])->conforms());
        self::assertNull($this->answered([])->conforms());
        self::assertNull($this->answered(['Gotenberg-Remediation-Residual' => 'x'])->residual);
        self::assertNull($this->answered(['Gotenberg-Remediation-Rolled-Back' => '-1'])->rolledBack);
    }

    public function testAZipIsToldFromOneFile(): void
    {
        self::assertTrue($this->answered(['Content-Type' => 'application/zip'])->isZip());
        self::assertFalse($this->answered(['Content-Type' => 'application/pdf'])->isZip());
        self::assertFalse($this->answered(['Content-Type' => 'text/plain; charset=utf-8; application/zip'])->isZip());
        self::assertSame('', $this->answered([])->contentType);
    }

    public function testAWebhookLeavesTheResultDeferred(): void
    {
        $this->server->answer('', 204, []);
        $gotenberg = new Gotenberg($this->server->client, 'http://gotenberg:3000');

        $result = $gotenberg->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge(), new RequestOptions(webhook: new Webhook('https://app.example.com/u', 'https://app.example.com/e')));

        self::assertTrue($result->deferred);
        self::assertSame('', $result->contents());
        self::assertSame(['https://app.example.com/u'], $this->server->last()['headers']['gotenberg-webhook-url']);
    }

    public function testTheResultIsStreamedToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-paged-media-');
        self::assertIsString($path);

        try {
            $this->server->answerWith(new MockResponse(['%PDF', '-1.7', ' body'], ['response_headers' => ['Content-Type' => 'application/pdf']]));
            $result = new Gotenberg($this->server->client, 'http://gotenberg:3000')->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());
            file_put_contents($path, 'longer previous content');

            $result->saveTo($path);

            self::assertSame('%PDF-1.7 body', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testAResultCannotBeSavedWhereNothingCanBeWritten(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('The file "/nonexistent/dir/out.pdf" cannot be written.');

        $this->answered([])->saveTo('/nonexistent/dir/out.pdf');
    }

    public function testAResultCutShortIsWorthRetrying(): void
    {
        $this->server->answerWith(new MockResponse(['%PDF', new RuntimeException('Reset by peer')], ['response_headers' => ['Content-Type' => 'application/pdf']]));
        $result = new Gotenberg($this->server->client, 'http://gotenberg:3000')->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());

        try {
            $result->contents();
            self::fail('A result cut short was read whole.');
        } catch (ServiceUnavailable $serviceUnavailable) {
            self::assertStringStartsWith('Gotenberg could not be reached at http://gotenberg:3000/forms/pdf/merge: ', $serviceUnavailable->getMessage());
        }
    }

    public function testAResultCutShortWhileSavingIsWorthRetrying(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-paged-media-');
        self::assertIsString($path);
        $this->server->answerWith(new MockResponse(['%PDF', new RuntimeException('Reset by peer')], ['response_headers' => ['Content-Type' => 'application/pdf']]));
        $result = new Gotenberg($this->server->client, 'http://gotenberg:3000')->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());

        try {
            $result->saveTo($path);
            self::fail('A result cut short was saved whole.');
        } catch (ServiceUnavailable $serviceUnavailable) {
            self::assertStringStartsWith('Gotenberg could not be reached at http://gotenberg:3000/forms/pdf/merge: ', $serviceUnavailable->getMessage());
            self::assertSame('%PDF', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function answered(array $headers): Result
    {
        $this->server->answer('%PDF', 200, $headers);

        return new Gotenberg($this->server->client, 'http://gotenberg:3000')->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());
    }
}
