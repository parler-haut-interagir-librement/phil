<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Cmi5;

/**
 * The verbs of the statements cmi5 defines, by their IRI, and who may issue each: the assignable unit, or the
 * platform.
 */
enum Cmi5Verb: string
{
    case Launched = 'http://adlnet.gov/expapi/verbs/launched';
    case Initialized = 'http://adlnet.gov/expapi/verbs/initialized';
    case Completed = 'http://adlnet.gov/expapi/verbs/completed';
    case Passed = 'http://adlnet.gov/expapi/verbs/passed';
    case Failed = 'http://adlnet.gov/expapi/verbs/failed';
    case Abandoned = 'https://w3id.org/xapi/adl/verbs/abandoned';
    case Waived = 'https://w3id.org/xapi/adl/verbs/waived';
    case Terminated = 'http://adlnet.gov/expapi/verbs/terminated';
    case Satisfied = 'https://w3id.org/xapi/adl/verbs/satisfied';

    /**
     * Whether only the platform issues it: an assignable unit that sends it is refused.
     */
    public function isIssuedByThePlatform(): bool
    {
        return match ($this) {
            self::Launched, self::Abandoned, self::Waived, self::Satisfied => true,
            default => false,
        };
    }

    /**
     * Whether the statement weighs in the `moveOn` rule, and carries the `moveon` category.
     */
    public function movesOn(): bool
    {
        return match ($this) {
            self::Completed, self::Passed, self::Failed => true,
            default => false,
        };
    }

    public function display(): string
    {
        return strtolower($this->name);
    }
}
