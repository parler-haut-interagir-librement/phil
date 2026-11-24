<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Exception;

use Phil\SurveyBundle\Domain\Exception\SurveyException;
use RuntimeException;

/**
 * No questionnaire, or no template, has this key.
 */
final class UnknownQuestionnaire extends RuntimeException implements SurveyException
{
    public static function withKey(string $key): self
    {
        return new self(\sprintf('No questionnaire has the key "%s".', mb_substr($key, 0, 80)));
    }

    public static function template(string $name): self
    {
        return new self(\sprintf('No template is named "%s".', mb_substr($name, 0, 80)));
    }
}
