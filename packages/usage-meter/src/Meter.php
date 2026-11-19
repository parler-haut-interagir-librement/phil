<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Period\PeriodPolicy;

/**
 * What is counted, over which period, and how much a subject may use when nothing else says: projects created,
 * exports made, tokens spent, requests sent.
 */
final readonly class Meter
{
    public function __construct(
        /** Up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter or a digit. */
        public string $name,
        public PeriodPolicy $period,
        /** The limit of a subject that no limit provider names. */
        public Limit $limit,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidUsage::because(\sprintf('"%s" is not a meter name: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter or a digit.', $name));
        }
    }
}
