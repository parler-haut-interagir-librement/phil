<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit\Fixture;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;

/**
 * Events built by hand, for the tests.
 */
final class Events
{
    private function __construct()
    {
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public static function one(
        string $message = 'User 42 not found',
        ?string $release = '1.0.0',
        string $at = '2026-11-08T10:00:00+00:00',
        ?RequestSummary $request = null,
        array $context = [],
        string $type = 'RuntimeException',
    ): ErrorEvent {
        return new ErrorEvent(
            str_repeat('a', 32),
            new DateTimeImmutable($at),
            Source::Http,
            Severity::Error,
            [new ExceptionRecord($type, $message, [
                new Frame('vendor/lib/Client.php', 10, 'Lib\Client::send'),
                new Frame('src/Export.php', 20, 'App\Export::run', true),
            ])],
            $release,
            'prod',
            $request,
            $context,
        );
    }
}
