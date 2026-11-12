<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Validator;

use Attribute;
use Override;
use Phil\EmailAddress\Problem;
use Symfony\Component\Validator\Constraint;

/**
 * A value that is an email address a mail server can deliver to: its syntax (RFC 5321, with the forms of RFC 6531 the
 * options allow), a top-level domain the root zone delegates, no domain the application refuses, no disposable
 * domain, and, when asked, a mail server for its domain. A violation names the first problem found; `{{ suggestion }}`
 * holds the address the person probably meant, or an empty string.
 *
 * Null and the empty string are valid: add `NotBlank` to require a value.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class DeliverableEmailAddress extends Constraint
{
    public const string SYNTAX_ERROR = '3d6c8081-29bb-4f0e-a1f6-23d40bdedfb3';

    public const string UNKNOWN_TOP_LEVEL_DOMAIN_ERROR = '43d2a225-f18a-4ba0-9c1b-43ed8712b613';

    public const string DENIED_DOMAIN_ERROR = 'a8c189fe-3a34-44ee-9001-6687e342355a';

    public const string DOMAIN_NOT_ALLOWED_ERROR = '51cca9e0-285b-4ba7-a576-6373692e7193';

    public const string DISPOSABLE_ERROR = '1377ccc9-4f41-41b3-b9db-7ad2fa4781c2';

    public const string NO_MAIL_SERVER_ERROR = 'd467b511-3029-41b2-b145-c0fe8c0d0b02';

    public const string REFUSES_MAIL_ERROR = '8e52b526-8938-4d74-9435-2ba8b5d32449';

    protected const array ERROR_NAMES = [
        self::SYNTAX_ERROR => 'SYNTAX_ERROR',
        self::UNKNOWN_TOP_LEVEL_DOMAIN_ERROR => 'UNKNOWN_TOP_LEVEL_DOMAIN_ERROR',
        self::DENIED_DOMAIN_ERROR => 'DENIED_DOMAIN_ERROR',
        self::DOMAIN_NOT_ALLOWED_ERROR => 'DOMAIN_NOT_ALLOWED_ERROR',
        self::DISPOSABLE_ERROR => 'DISPOSABLE_ERROR',
        self::NO_MAIL_SERVER_ERROR => 'NO_MAIL_SERVER_ERROR',
        self::REFUSES_MAIL_ERROR => 'REFUSES_MAIL_ERROR',
    ];

    public string $message = 'This value is not a valid email address.';

    public string $unknownTopLevelDomainMessage = 'The domain {{ domain }} does not end with a known top-level domain.';

    public string $deniedDomainMessage = 'Addresses at {{ domain }} are not accepted.';

    public string $domainNotAllowedMessage = 'Addresses at {{ domain }} are not accepted here.';

    public string $disposableMessage = 'Disposable email addresses are not accepted.';

    public string $noMailServerMessage = 'No mail server receives mail for {{ domain }}.';

    public string $refusesMailMessage = 'The domain {{ domain }} receives no mail.';

    /**
     * @param list<string>      $deniedDomains  domains refused, with their subdomains
     * @param list<string>      $allowedDomains the only domains accepted, with their subdomains; none accepts every domain
     * @param list<string>|null $groups
     */
    public function __construct(
        public bool $internationalLocalPart = false,
        public bool $quotedLocalPart = false,
        public bool $ipLiteral = false,
        public bool $allowDisposable = false,
        public bool $knownTopLevelDomain = true,
        public bool $checkMailServer = false,
        public array $deniedDomains = [],
        public array $allowedDomains = [],
        ?string $message = null,
        ?string $unknownTopLevelDomainMessage = null,
        ?string $deniedDomainMessage = null,
        ?string $domainNotAllowedMessage = null,
        ?string $disposableMessage = null,
        ?string $noMailServerMessage = null,
        ?string $refusesMailMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->unknownTopLevelDomainMessage = $unknownTopLevelDomainMessage ?? $this->unknownTopLevelDomainMessage;
        $this->deniedDomainMessage = $deniedDomainMessage ?? $this->deniedDomainMessage;
        $this->domainNotAllowedMessage = $domainNotAllowedMessage ?? $this->domainNotAllowedMessage;
        $this->disposableMessage = $disposableMessage ?? $this->disposableMessage;
        $this->noMailServerMessage = $noMailServerMessage ?? $this->noMailServerMessage;
        $this->refusesMailMessage = $refusesMailMessage ?? $this->refusesMailMessage;
    }

    /**
     * @return array{string, string} the message and the code of a problem
     */
    public function violationOf(Problem $problem): array
    {
        return match ($problem) {
            Problem::Syntax => [$this->message, self::SYNTAX_ERROR],
            Problem::UnknownTopLevelDomain => [$this->unknownTopLevelDomainMessage, self::UNKNOWN_TOP_LEVEL_DOMAIN_ERROR],
            Problem::DeniedDomain => [$this->deniedDomainMessage, self::DENIED_DOMAIN_ERROR],
            Problem::DomainNotAllowed => [$this->domainNotAllowedMessage, self::DOMAIN_NOT_ALLOWED_ERROR],
            Problem::Disposable => [$this->disposableMessage, self::DISPOSABLE_ERROR],
            Problem::NoMailServer => [$this->noMailServerMessage, self::NO_MAIL_SERVER_ERROR],
            Problem::RefusesMail => [$this->refusesMailMessage, self::REFUSES_MAIL_ERROR],
        };
    }

    #[Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
