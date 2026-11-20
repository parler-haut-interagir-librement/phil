<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * A recoverable issue of an xAPI package: what it is, the activity it concerns, and a sentence that says it.
 */
final readonly class XapiWarning
{
    public function __construct(
        public XapiWarningKind $kind,
        public string $activity,
        public string $message,
    ) {
    }
}
