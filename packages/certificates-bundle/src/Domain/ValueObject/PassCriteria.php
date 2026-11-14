<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * What a learner attains to pass a course: a minimum score in percent, a minimum of minutes of training, and modules
 * to complete — each optional; a course without any is passed by completing it.
 */
final readonly class PassCriteria
{
    public const int MAX_MODULES = 100;

    /**
     * @param list<string> $requiredModules the codes of the modules to complete, each up to 64 characters
     */
    public function __construct(
        public ?int $minimumScore = null,
        public ?int $minimumMinutes = null,
        public array $requiredModules = [],
    ) {
        if (null !== $minimumScore && ($minimumScore < 1 || $minimumScore > 100)) {
            throw InvalidValue::because(\sprintf('A minimum score is from 1 to 100; %d is not.', $minimumScore));
        }

        if (null !== $minimumMinutes && $minimumMinutes < 1) {
            throw InvalidValue::because('A minimum of minutes of training is at least 1.');
        }

        if (\count($requiredModules) > self::MAX_MODULES) {
            throw InvalidValue::because(\sprintf('A course requires at most %d modules.', self::MAX_MODULES));
        }

        foreach ($requiredModules as $module) {
            if ('' === trim($module) || mb_strlen($module) > 64) {
                throw InvalidValue::because('The code of a module has from 1 to 64 characters.');
            }
        }

        if (\count(array_unique($requiredModules)) !== \count($requiredModules)) {
            throw InvalidValue::because('A module is required once.');
        }
    }

    /**
     * What the attainment lacks: `score`, `minutes`, and `module:<code>` for each module not completed, in that order;
     * an empty list when it passes.
     *
     * @return list<string>
     */
    public function unmet(Attainment $attainment): array
    {
        $unmet = [];

        if (null !== $this->minimumScore && (null === $attainment->score || $attainment->score < $this->minimumScore)) {
            $unmet[] = 'score';
        }

        if (null !== $this->minimumMinutes && $attainment->minutes < $this->minimumMinutes) {
            $unmet[] = 'minutes';
        }

        foreach (array_diff($this->requiredModules, $attainment->completedModules) as $module) {
            $unmet[] = 'module:'.$module;
        }

        return $unmet;
    }

    public function isMetBy(Attainment $attainment): bool
    {
        return [] === $this->unmet($attainment);
    }
}
