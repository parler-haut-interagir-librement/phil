<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Exception;

use Phil\ShortLinkBundle\Domain\Exception\InvalidShortLink;
use RuntimeException;

/**
 * A short link the application refuses to save: its reason is a short key — `target`, `code_taken`, `rate_limited`… —
 * and its message names the rule, with its parameters, so that a form can translate it under `refused.<reason>`.
 */
final class ShortLinkRefused extends RuntimeException
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

    public static function from(InvalidShortLink $invalid): self
    {
        return new self($invalid->reason, $invalid->template, $invalid->parameters);
    }
}
