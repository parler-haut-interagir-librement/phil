<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Fixture;

use Override;
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use RuntimeException;

/**
 * The visits it was told of, or a failure on each.
 */
final class RecordingRecorder implements VisitRecorder
{
    /** @var list<ShortLinkVisit> */
    public array $visits = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {
    }

    public function told(): int
    {
        return \count($this->visits);
    }

    #[Override]
    public function record(ShortLinkVisit $visit): void
    {
        if ($this->fails) {
            throw new RuntimeException('The recorder is down.');
        }

        $this->visits[] = $visit;
    }
}
