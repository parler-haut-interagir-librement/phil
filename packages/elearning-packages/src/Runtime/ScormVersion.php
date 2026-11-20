<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;
use Phil\ElearningPackages\PackageFormat;

/**
 * The two run times of SCORM: SCORM 1.2, whose content finds the object `API` and calls `LMSInitialize()`, and SCORM
 * 2004 4th edition, whose content finds `API_1484_11` and calls `Initialize()`. Each has its data model, its error
 * codes and its formats of time.
 */
enum ScormVersion: string
{
    case Scorm12 = '1.2';
    case Scorm2004 = '2004';

    /**
     * @throws RuntimeRefused when the format is not a SCORM one
     */
    public static function of(PackageFormat $format): self
    {
        return match ($format) {
            PackageFormat::Scorm12 => self::Scorm12,
            PackageFormat::Scorm2004 => self::Scorm2004,
            PackageFormat::Cmi5, PackageFormat::Xapi => throw RuntimeRefused::because('A cmi5 unit or an xAPI package talks xAPI, not the SCORM run-time API.'),
        };
    }

    /**
     * The name of the object the content looks for in the windows that frame it.
     */
    public function apiName(): string
    {
        return self::Scorm12 === $this ? 'API' : 'API_1484_11';
    }

    /**
     * What `cmi._version` answers.
     */
    public function dataModelVersion(): string
    {
        return self::Scorm12 === $this ? '3.4' : '1.0';
    }

    /**
     * The element that holds where the learner stands: `cmi.core.lesson_status`, or `cmi.completion_status`.
     */
    public function statusElement(): string
    {
        return self::Scorm12 === $this ? 'cmi.core.lesson_status' : 'cmi.completion_status';
    }

    /**
     * The prefix of the elements of the learner's session in SCORM 1.2 (`cmi.core.`), none in SCORM 2004.
     */
    public function core(string $element): string
    {
        return self::Scorm12 === $this ? 'cmi.core.'.$element : 'cmi.'.$element;
    }
}
