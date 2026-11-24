<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Redaction\ContextRedactor;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;
use Phil\PrivacyBundle\Domain\Redaction\UrlRedactor;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedactingProcessor::class)]
final class RedactingProcessorTest extends TestCase
{
    public function testTheMessageTheContextAndTheExtraAreRedacted(): void
    {
        $processor = new RedactingProcessor(new ContextRedactor(Denylist::of('password'), new UrlRedactor(['/p/'])));
        $record = new LogRecord(
            new DateTimeImmutable('2026-10-31T00:00:00Z'),
            'app',
            Level::Warning,
            'Login failed at /login?next=/p/abc',
            ['password' => 'hunter2', 'user' => 'u-1'],
            ['url' => '/p/abc?lang=fr'],
        );

        $processed = $processor($record);

        self::assertSame('Login failed at /login?[redacted]', $processed->message);
        self::assertSame(['password' => '[redacted]', 'user' => 'u-1'], $processed->context);
        self::assertSame(['url' => '/p/[redacted]?[redacted]'], $processed->extra);
        self::assertSame('app', $processed->channel);
        self::assertSame(Level::Warning, $processed->level);
        self::assertSame($record->datetime, $processed->datetime);
    }

    public function testTheConfiguredProcessorRedactsTheDeniedKeysAndTheTokenSegments(): void
    {
        $processor = RedactingProcessor::fromConfiguration(['Authorization'], ['/p/']);
        $record = new LogRecord(
            new DateTimeImmutable('2026-10-31T00:00:00Z'),
            'app',
            Level::Info,
            'Opened /p/abc/edit',
            ['authorization' => 'Bearer x', 'user' => 'u-1'],
        );

        $processed = $processor($record);

        self::assertSame('Opened /p/[redacted]/edit', $processed->message);
        self::assertSame(['authorization' => '[redacted]', 'user' => 'u-1'], $processed->context);
    }

    public function testTheConfiguredProcessorRedactsADeclaredShortOption(): void
    {
        $processor = RedactingProcessor::fromConfiguration(['password'], [], ['p' => 'password']);
        $record = new LogRecord(
            new DateTimeImmutable('2026-10-31T00:00:00Z'),
            'console',
            Level::Error,
            'Error thrown while running command "app:import -p hunter2".',
            ['command' => 'app:import -phunter2'],
        );

        $processed = $processor($record);

        self::assertSame('Error thrown while running command "app:import -p [redacted]".', $processed->message);
        self::assertSame(['command' => 'app:import -p[redacted]'], $processed->context);
    }

    public function testTheCategoriesOfPersonalDataAreDeniedKeysAndAMarkerNeverReachesTheRecord(): void
    {
        $processor = RedactingProcessor::fromConfiguration([], [], [], RedactingProcessor::PERSONAL_DATA_CATEGORIES);
        $marker = 'marker-7f3a@example.com';
        $record = new LogRecord(
            new DateTimeImmutable('2026-10-31T00:00:00Z'),
            'app',
            Level::Warning,
            'Sign-in refused.',
            ['email' => $marker, 'personName' => 'Ada', 'Phone' => '+15145550123', 'IP' => '192.0.2.7', 'request' => ['nickname' => 'ada'], 'account' => 'a-1', 'emailCount' => 2],
            ['contact' => ['email' => $marker]],
        );

        $processed = $processor($record);

        self::assertSame(['email' => '[redacted]', 'personName' => '[redacted]', 'Phone' => '[redacted]', 'IP' => '[redacted]', 'request' => ['nickname' => '[redacted]'], 'account' => 'a-1', 'emailCount' => 2], $processed->context);
        self::assertSame(['contact' => '[redacted]'], $processed->extra);
        self::assertStringNotContainsString($marker, (string) json_encode([$processed->context, $processed->extra]));
    }

    public function testAMalformedCategoryIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RedactingProcessor::fromConfiguration([], [], [], ['E-mail']);
    }

    public function testAMalformedPrefixIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RedactingProcessor::fromConfiguration([], ['p']);
    }
}
