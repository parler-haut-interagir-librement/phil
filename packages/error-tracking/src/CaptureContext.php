<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * What the code that caught an error knows around it: where it was caught, how grave it is, the request, and tags of
 * its own — a message class, a command name, a transport.
 */
final readonly class CaptureContext
{
    /**
     * @param array<string, scalar|null> $context
     */
    public function __construct(
        public Source $source = Source::Manual,
        public Severity $severity = Severity::Error,
        public ?RequestSummary $request = null,
        public array $context = [],
    ) {
    }
}
