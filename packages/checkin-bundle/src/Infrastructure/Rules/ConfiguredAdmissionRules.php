<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Rules;

use Override;
use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\Application\Contract\AdmissionRules;

/**
 * The admission rules of the configuration, `phil_checkin.rules.checkpoints`, by checkpoint name.
 */
final readonly class ConfiguredAdmissionRules implements AdmissionRules
{
    /**
     * @param array<string, string> $rules the expression of each checkpoint, by its name
     */
    public function __construct(
        private array $rules = [],
    ) {
    }

    #[Override]
    public function ruleFor(CheckpointId $checkpoint): ?string
    {
        return $this->rules[$checkpoint->value] ?? null;
    }
}
