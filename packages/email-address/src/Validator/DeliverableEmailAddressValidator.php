<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Validator;

use Override;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\DomainRules;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\SyntaxPolicy;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\TypoSuggester;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks `DeliverableEmailAddress` through `EmailValidator`. Built without arguments, as the validator of Symfony builds
 * it outside a container, it reads the lists bundled in `data/` once and asks the DNS through `dns_get_record()` when
 * a constraint wants a mail server; a container passes its own lists, resolver and suggester.
 *
 * The lists it keeps are the same for every request; nothing of one value is kept for the next.
 */
final class DeliverableEmailAddressValidator extends ConstraintValidator
{
    public function __construct(
        private ?DisposableDomains $disposableDomains = null,
        private ?TopLevelDomains $topLevelDomains = null,
        private ?MailDomainResolver $resolver = null,
        private readonly ?TypoSuggester $suggester = new TypoSuggester(),
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DeliverableEmailAddress) {
            throw new UnexpectedTypeException($constraint, DeliverableEmailAddress::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $text = $value instanceof EmailAddress ? $value->toAscii() : (string) $value;
        $validation = $this->validatorFor($constraint)->validate($text);

        if ($validation->isValid()) {
            return;
        }

        [$message, $code] = $constraint->violationOf($validation->problems[0]);

        $this->context->buildViolation($message)
            ->setParameter('{{ value }}', $this->formatValue($text))
            ->setParameter('{{ domain }}', $this->formatValue($validation->address->domain ?? ''))
            ->setParameter('{{ reason }}', $validation->syntaxError?->describe() ?? '')
            ->setParameter('{{ suggestion }}', $validation->suggestion?->toString() ?? '')
            ->setCode($code)
            ->addViolation();
    }

    private function validatorFor(DeliverableEmailAddress $constraint): EmailValidator
    {
        $policy = new SyntaxPolicy()
            ->withInternationalLocalPart($constraint->internationalLocalPart)
            ->withQuotedLocalPart($constraint->quotedLocalPart)
            ->withIpLiteral($constraint->ipLiteral);

        return new EmailValidator(
            $policy,
            DomainRules::of($constraint->deniedDomains, $constraint->allowedDomains),
            $constraint->allowDisposable ? null : ($this->disposableDomains ??= DisposableDomains::bundled()),
            $constraint->checkMailServer ? ($this->resolver ??= new DnsGetRecordResolver()) : null,
            $this->suggester,
            $constraint->knownTopLevelDomain ? ($this->topLevelDomains ??= TopLevelDomains::bundled()) : null,
        );
    }
}
