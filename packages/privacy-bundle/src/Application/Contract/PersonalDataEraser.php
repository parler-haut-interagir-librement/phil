<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * One module's part of an erasure.
 *
 * **Must be idempotent.** An erasure that fails halfway is retried, and an erasure is replayed after a backup
 * restore: running it a second time on an already erased person must succeed and report the same effects.
 */
interface PersonalDataEraser
{
    public function module(): ModuleName;

    public function erase(DataSubject $subject): ErasureReport;
}
