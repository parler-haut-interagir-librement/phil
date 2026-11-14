<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * Assembles an access or portability answer from every module's exporter.
 *
 * The result is one section per module, keyed by module name and sorted, so two exports of the same data are the
 * same document. A module that holds nothing on the person still has its section, empty: the answer says the
 * module was asked.
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
     */
    public function export(DataSubject $subject): array
    {
        $sections = [];

        foreach ($this->exporters as $module => $exporter) {
            $sections[$module] = $exporter->export($subject);
        }

        return $sections;
    }
}
