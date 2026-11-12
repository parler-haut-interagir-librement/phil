<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

/**
 * What the validator found of one address: the address when its syntax holds, the problems that refuse it, the
 * address the person probably meant, and what the DNS said of its domain when it was asked.
 */
final readonly class Validation
{
    /**
     * @param list<Problem> $problems
     */
    public function __construct(
        public ?EmailAddress $address,
        public ?SyntaxError $syntaxError,
        public array $problems,
        public ?EmailAddress $suggestion,
        public ?MailDomain $mailDomain,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->problems;
    }

    public function has(Problem $problem): bool
    {
        return \in_array($problem, $this->problems, true);
    }
}
