<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

/**
 * What a check found wrong with a target: the name of the check — which the flag `ignore-<name>` dismisses — and a
 * sentence that says what, in English.
 */
final readonly class CheckIssue
{
    public function __construct(
        public string $check,
        public string $message,
    ) {
    }
}
