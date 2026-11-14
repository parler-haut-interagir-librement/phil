<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * The processor the bundle tags, found by MonologBundle and run on every record before the handler writes it:
 * records the application logs itself, and records the framework logs while it serves a request.
 */
#[CoversClass(PhilPrivacyBundle::class)]
#[CoversClass(RedactingProcessor::class)]
final class LogRedactionTest extends FunctionalTestCase
{
    public function testTheProcessorIsBuiltFromTheConfiguration(): void
    {
        $processor = $this->service(RedactingProcessor::class, RedactingProcessor::class);

        self::assertSame(['plainPassword' => '[redacted]'], $processor(new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'x', ['plainPassword' => 'hunter2']))->context);
    }

    public function testARecordOfTheApplicationIsRedactedBeforeItIsWritten(): void
    {
        $this->logger()->info('Shared link opened: https://shop.example/p/abc123/edit?ref=mail', [
            'email' => 'someone@example.com',
            'refresh_token' => 't0k3n',
            'command' => 'app:import -p hunter2 --db-password=hunter2 --dry-run',
            'nested' => ['plain_password' => 'hunter2', 'country' => 'CA'],
        ]);

        $record = $this->onlyRecord('Shared link opened');

        self::assertSame('Shared link opened: https://shop.example/p/[redacted]/edit?[redacted]', $record->message);
        self::assertSame([
            'email' => '[redacted]',
            'refresh_token' => '[redacted]',
            'command' => 'app:import -p [redacted] --db-password=[redacted] --dry-run',
            'nested' => ['plain_password' => '[redacted]', 'country' => 'CA'],
        ], $record->context);
    }

    public function testAThrowableInTheContextIsRedacted(): void
    {
        $this->logger()->error('Import failed.', ['exception' => new RuntimeException('Cannot read https://user:secret@files.example/p/abc?sig=1')]);

        $exception = $this->onlyRecord('Import failed.')->context['exception'];

        self::assertIsArray($exception);
        self::assertSame('Cannot read https://[redacted]@files.example/p/[redacted]?[redacted]', $exception['message']);
    }

    /**
     * The router logs the URI it matched: the token in the path and the query string never reach the handler.
     */
    public function testTheUriTheFrameworkLogsDuringARequestIsRedacted(): void
    {
        self::assertSame(200, $this->request('/p/s3cr3t-link?email=someone@example.com')->getStatusCode());

        $record = $this->onlyRecord('Matched route');

        self::assertSame('http://localhost/p/[redacted]?[redacted]', $record->context['request_uri'] ?? null);
        self::assertStringNotContainsString('s3cr3t-link', $this->allLogged());
        self::assertStringNotContainsString('someone@example.com', $this->allLogged());
    }

    /**
     * A 404 is logged with the URI in the exception message, by the error listener rather than by the router.
     */
    public function testTheUriOfANotFoundErrorIsRedacted(): void
    {
        self::assertSame(404, $this->request('/p/s3cr3t-link/missing')->getStatusCode());

        $exception = $this->onlyRecord('Uncaught PHP Exception')->context['exception'];

        self::assertIsArray($exception);
        self::assertSame('No route found for "GET http://localhost/p/[redacted]/missing"', $exception['message']);
        self::assertStringNotContainsString('s3cr3t-link', $this->allLogged());
    }

    private function onlyRecord(string $messageStart): LogRecord
    {
        $records = array_values(array_filter($this->records(), static fn (LogRecord $record): bool => str_starts_with($record->message, $messageStart)));
        self::assertCount(1, $records);

        return $records[0];
    }

    private function allLogged(): string
    {
        return json_encode(array_map(static fn (LogRecord $record): array => $record->toArray(), $this->records()), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
