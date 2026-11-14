<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Handler;

use Phil\PrivacyBundle\Application\RetentionJob;

/**
 * Handles `RunRetention`, which carries nothing: the tag of the service names the message it handles.
 */
final readonly class RunRetentionHandler
{
    public function __construct(
        private RetentionJob $job,
    ) {
    }

    public function __invoke(): void
    {
        $this->job->run();
    }
}
