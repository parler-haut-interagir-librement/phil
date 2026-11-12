<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\Exception\InvalidEmailAddress;

/**
 * Checks an address in order, each step only when the ones before it passed: its syntax, its top-level domain, the
 * application's domain rules, the disposable domains, then the DNS of its domain. An empty list of top-level domains
 * checks nothing. A suggestion is looked for whatever the outcome, so that
 * `ada@gmial.com` and `ada@gmailcom` both get one.
 *
 * A lookup that fails says nothing: `MailDomain::Unknown` refuses no address. The validator keeps no state between
 * calls.
 */
final readonly class EmailValidator
{
    public function __construct(
        private SyntaxPolicy $policy = new SyntaxPolicy(),
        private ?DomainRules $rules = null,
        private ?DisposableDomains $disposable = null,
        private ?MailDomainResolver $resolver = null,
        private ?TypoSuggester $suggester = new TypoSuggester(),
        private ?TopLevelDomains $topLevelDomains = null,
    ) {
    }

    public function validate(string $input): Validation
    {
        $suggestion = $this->suggester?->suggest($input);

        try {
            $address = EmailAddress::parse($input, $this->policy);
        } catch (InvalidEmailAddress $invalidEmailAddress) {
            return new Validation(null, $invalidEmailAddress->reason, [Problem::Syntax], $suggestion, null);
        }

        $problem = $this->unknownTopLevelDomain($address)
            ?? $this->rules?->check($address)
            ?? (true === $this->disposable?->contains($address) ? Problem::Disposable : null);

        if ($problem instanceof Problem || !$this->resolver instanceof MailDomainResolver || $address->isIpLiteral()) {
            return new Validation($address, null, $problem instanceof Problem ? [$problem] : [], $suggestion, null);
        }

        $mailDomain = $this->resolver->resolve($address->asciiDomain);
        $problems = match ($mailDomain) {
            MailDomain::NullMx => [Problem::RefusesMail],
            MailDomain::NoRecord => [Problem::NoMailServer],
            default => [],
        };

        return new Validation($address, null, $problems, $suggestion, $mailDomain);
    }

    private function unknownTopLevelDomain(EmailAddress $address): ?Problem
    {
        if (!$this->topLevelDomains instanceof TopLevelDomains || $this->topLevelDomains->isEmpty() || $address->isIpLiteral()) {
            return null;
        }

        return $this->topLevelDomains->contains($address) ? null : Problem::UnknownTopLevelDomain;
    }
}
