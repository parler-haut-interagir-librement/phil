<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Throwable;
use UnexpectedValueException;

/**
 * Runs every module's eraser for one person.
 *
 * **One failing module does not stop the others.** Every eraser runs; if any failed, `ErasureIncomplete` carries
 * the reports of those that succeeded and the failure of each that did not. Stopping at the first failure would
 * leave the later modules untouched for no reason, and a retry runs them all again anyway, which is why erasers
 * are idempotent.
 */
final readonly class SubjectErasure
{
    /**
     * @var array<string, PersonalDataEraser>
     */
    private array $erasers;

    /**
     * @param iterable<PersonalDataEraser> $erasers
     */
    public function __construct(iterable $erasers)
    {
        $byModule = [];

        foreach ($erasers as $eraser) {
            $module = $eraser->module()->value;

            if (isset($byModule[$module])) {
                throw MisconfiguredPrivacy::duplicateModule($module);
            }

            $byModule[$module] = $eraser;
        }

        ksort($byModule, \SORT_STRING);
        $this->erasers = $byModule;
    }

    /**
     * @return list<ErasureReport> one per module, sorted by module name
     *
     * @throws ErasureIncomplete
     */
    public function erase(DataSubject $subject): array
    {
        $reports = [];
        $failures = [];

        foreach ($this->erasers as $module => $eraser) {
            try {
                $report = $eraser->erase($subject);

                // A report filed under another module's name would mark that module done when it never ran.
                if ($report->module->value !== $module) {
                    throw new UnexpectedValueException(\sprintf('The eraser of module "%s" returned a report for module "%s".', $module, $report->module->value));
                }

                $reports[] = $report;
            } catch (Throwable $failure) {
                $failures[$module] = $failure;
            }
        }

        if ([] !== $failures) {
            throw ErasureIncomplete::after($reports, $failures);
        }

        return $reports;
    }
}
