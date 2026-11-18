<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * Assembles an access or portability answer from every module's exporter.
 *
 * The result is one section per module, keyed by module name and sorted, so two exports of the same data are the
 * same document. A module that holds nothing on the person still has its section, empty: the answer says the
 * module was asked.
 *
 * Under a held lock, the lock is renewed before each module: an export that lost it stops before the next module.
 */
final readonly class SubjectExport
{
    /**
     * @var array<string, PersonalDataExporter>
     */
    private array $exporters;

    /**
     * @param iterable<PersonalDataExporter> $exporters
     */
    public function __construct(iterable $exporters)
    {
        $byModule = [];

        foreach ($exporters as $exporter) {
            $module = $exporter->module()->value;

            if (isset($byModule[$module])) {
                throw MisconfiguredPrivacy::duplicateModule($module);
            }

            $byModule[$module] = $exporter;
        }

        ksort($byModule, \SORT_STRING);
        $this->exporters = $byModule;
    }

    /**
     * @return array<string, array<string, mixed>> by module name
     *
     * @throws LockLost when the held lock is lost before a module
     */
    public function export(DataSubject $subject, ?HeldLock $lock = null): array
    {
        $sections = [];

        foreach ($this->exporters as $module => $exporter) {
            $lock?->renew();
            $sections[$module] = $exporter->export($subject);
        }

        return $sections;
    }
}
