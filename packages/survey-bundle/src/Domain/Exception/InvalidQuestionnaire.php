<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Exception;

use DomainException;

/**
 * A questionnaire, or its JSON, breaks a rule: the message names it.
 */
final class InvalidQuestionnaire extends DomainException implements SurveyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
