<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check\Contract;

use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\CheckIssue;

/**
 * One quality check of a translated segment. A check has a name — the flag `ignore-<name>` dismisses it on a segment —
 * and reports what it finds wrong with the target against its source; nothing when the target passes.
 */
interface Check
{
    /**
     * The name of the check, lower case letters and hyphens: `punctuation`, `max-size`.
     */
    public function name(): string;

    /**
     * @return list<CheckIssue>
     */
    public function check(Checked $checked): array;
}
