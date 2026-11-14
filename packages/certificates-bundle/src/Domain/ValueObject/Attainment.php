<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * What a learner attained in a course, as the application measured it: a score in percent when there was an
 * assessment, the minutes of training followed, and the codes of the modules completed.
 */
final readonly class Attainment
{
    /**
     * @param list<string> $completedModules
     */
    public function __construct(
        public ?int $score = null,
        public int $minutes = 0,
        public array $completedModules = [],
    ) {
        if (null !== $score && ($score < 0 || $score > 100)) {
            throw InvalidValue::because(\sprintf('A score is from 0 to 100; %d is not.', $score));
        }

        if ($minutes < 0) {
            throw InvalidValue::because('The minutes of training are not negative.');
        }
    }
}
