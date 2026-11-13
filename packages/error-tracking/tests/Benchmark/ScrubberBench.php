<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Benchmark;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Scrubber;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;

/**
 * Every captured error is scrubbed before it is written: a request with twenty headers and a query, ten tags, and a
 * chain of two messages.
 */
final readonly class ScrubberBench
{
    private ErrorEvent $event;

    private Scrubber $scrubber;

    public function __construct()
    {
        $headers = ['Authorization' => 'Bearer abc.def', 'Cookie' => 'PHPSESSID=1; theme=dark', 'Referer' => 'https://example.com/?from=jane@example.com'];

        for ($index = 0; $index < 17; ++$index) {
            $headers['X-Header-'.$index] = 'value '.$index;
        }

        $context = [];

        for ($index = 0; $index < 10; ++$index) {
            $context['tag_'.$index] = 'value '.$index;
        }

        $this->event = new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [
            new ExceptionRecord('RuntimeException', 'Mail to jane.doe@example.com failed with password=hunter2'),
            new ExceptionRecord('PDOException', 'Connect to mysql://app:s3cret@db/app failed'),
        ], '1.0.0', 'prod', new RequestSummary('POST', 'https://example.com/login?next=/me&token=abc', 'login', $headers, '192.0.2.0'), $context + ['password' => 'x']);
        $this->scrubber = new Scrubber();
    }

    public function benchScrubAnEvent(): void
    {
        $this->scrubber->scrub($this->event);
    }

    public function benchScrubAText(): void
    {
        $this->scrubber->text('Mail to jane.doe@example.com failed with password=hunter2 and Bearer abc.def');
    }
}
