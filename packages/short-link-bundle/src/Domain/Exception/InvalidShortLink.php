<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\Exception;

use DomainException;

/**
 * A short link that cannot exist as given: its reason is a short key, and its message names the rule, with its
 * parameters, so that a form can translate it.
 */
final class InvalidShortLink extends DomainException
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $template,
        public readonly array $parameters,
    ) {
        parent::__construct(strtr($template, $parameters));
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function because(string $reason, string $template, array $parameters = []): self
    {
        return new self($reason, $template, $parameters);
    }
}
