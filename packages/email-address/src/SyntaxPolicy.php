<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

/**
 * Which of the forms RFC 5321 and RFC 6531 allow an application accepts. The default accepts what nearly every mail
 * server delivers: a dot-atom of ASCII before the @, and a domain name, in any script, after it. Each `with` method
 * returns a new policy.
 */
final readonly class SyntaxPolicy
{
    public function __construct(
        public bool $internationalLocalPart = false,
        public bool $quotedLocalPart = false,
        public bool $ipLiteral = false,
    ) {
    }

    /**
     * Non-ASCII characters before the @ (RFC 6531): the address then needs a sender and servers that speak SMTPUTF8.
     */
    public function withInternationalLocalPart(bool $allowed = true): self
    {
        return new self($allowed, $this->quotedLocalPart, $this->ipLiteral);
    }

    /**
     * A quoted part before the @, `"ada lovelace"@example.com`.
     */
    public function withQuotedLocalPart(bool $allowed = true): self
    {
        return new self($this->internationalLocalPart, $allowed, $this->ipLiteral);
    }

    /**
     * An IP address in brackets for a domain, `ada@[192.0.2.1]` or `ada@[IPv6:2001:db8::1]`.
     */
    public function withIpLiteral(bool $allowed = true): self
    {
        return new self($this->internationalLocalPart, $this->quotedLocalPart, $allowed);
    }
}
