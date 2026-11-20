<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Runtime\Duration;

/**
 * The limits of an activity, `<imsss:limitConditions>`: how many attempts it allows — null for no limit, as `0` reads
 * —, and how long one attempt may last.
 */
final readonly class LimitConditions
{
    public function __construct(
        public ?int $attemptLimit = null,
        public ?Duration $attemptAbsoluteDurationLimit = null,
    ) {
    }
}
