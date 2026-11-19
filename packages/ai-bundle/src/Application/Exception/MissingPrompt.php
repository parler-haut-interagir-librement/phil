<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use LogicException;
use Phil\AiBundle\Domain\Exception\AiException;

/**
 * A task whose user prompt has no translation in the locale asked, nor in the translator's fallbacks.
 */
final class MissingPrompt extends LogicException implements AiException
{
    public static function of(string $kind, string $locale, string $domain): self
    {
        return new self(\sprintf('The task "%s" has no prompt "%s.user" in the translation domain "%s" for the locale "%s".', $kind, $kind, $domain, $locale));
    }
}
