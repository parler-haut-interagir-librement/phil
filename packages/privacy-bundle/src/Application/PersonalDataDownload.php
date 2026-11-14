<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Symfony\Component\Lock\LockFactory;

/**
 * A person's own export, as a ZIP archive with a readable `README.txt` and one JSON file per module, one at a time per
 * person: an export reads every module, and a page reloaded in a loop must not run a hundred of them.
 */
final readonly class PersonalDataDownload
{
    public const float LOCK_TTL = 300.0;

    public function __construct(
        private SubjectExport $export,
        private LockFactory $locks,
        private ExportPackage $package,
    ) {
    }

    /**
     * @return string|null the archive, or null when an export of this person is already running
     */
    public function of(DataSubject $subject): ?string
    {
        $lock = $this->locks->createLock('phil_privacy.export.'.hash('xxh128', $subject->value), self::LOCK_TTL, false);

        if (!$lock->acquire()) {
            return null;
        }

        try {
            return $this->package->zip($this->export->export($subject));
        } finally {
            $lock->release();
        }
    }
}
