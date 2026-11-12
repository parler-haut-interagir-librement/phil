<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit\Validator;

use Override;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddress\Problem;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\Validator\DeliverableEmailAddress;
use Phil\EmailAddress\Validator\DeliverableEmailAddressValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Stringable;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @extends ConstraintValidatorTestCase<DeliverableEmailAddressValidator>
 */
#[CoversClass(DeliverableEmailAddress::class)]
#[CoversClass(DeliverableEmailAddressValidator::class)]
final class DeliverableEmailAddressValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new DeliverableEmailAddressValidator(
            DisposableDomains::of(['mailinator.com']),
            TopLevelDomains::of(['com', 'net', 'org', 'example']),
            new StaticResolver([
                'example.com' => MailDomain::Mx,
                'example.net' => MailDomain::NullMx,
                'example.org' => MailDomain::NoRecord,
            ]),
        );
    }

    public function testAcceptsNullAndTheEmptyString(): void
    {
        $this->validator->validateInContext(null, new DeliverableEmailAddress(), $this->context);
        $this->validator->validateInContext('', new DeliverableEmailAddress(), $this->context);

        $this->assertNoViolation();
    }

    public function testAcceptsADeliverableAddress(): void
    {
        $this->validator->validateInContext('ada@example.com', new DeliverableEmailAddress(checkMailServer: true), $this->context);
        $this->validator->validateInContext(EmailAddress::parse('ada@example.com'), new DeliverableEmailAddress(), $this->context);

        $this->assertNoViolation();
    }

    public function testReadsAStringableValue(): void
    {
        $value = new class implements Stringable {
            public function __toString(): string
            {
                return 'ada@example.com';
            }
        };

        $this->validator->validateInContext($value, new DeliverableEmailAddress(), $this->context);

        $this->assertNoViolation();
    }

    public function testRefusesAnotherConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validateInContext('ada@example.com', new Email(), $this->context);
    }

    public function testRefusesAValueThatIsNotText(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validateInContext(new stdClass(), new DeliverableEmailAddress(), $this->context);
    }

    public function testNamesTheSyntaxErrorAndTheSuggestion(): void
    {
        $this->validator->validateInContext('ada@gmailcom', new DeliverableEmailAddress(message: 'Bad: {{ reason }}'), $this->context);

        $this->buildViolation('Bad: {{ reason }}')
            ->setParameter('{{ value }}', '"ada@gmailcom"')
            ->setParameter('{{ domain }}', '""')
            ->setParameter('{{ reason }}', 'the domain has no dot')
            ->setParameter('{{ suggestion }}', 'ada@gmail.com')
            ->setCode(DeliverableEmailAddress::SYNTAX_ERROR)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{string, DeliverableEmailAddress, string, string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'an unknown top-level domain' => ['ada@example.invalid', new DeliverableEmailAddress(unknownTopLevelDomainMessage: 'tld {{ domain }}'), 'tld {{ domain }}', '"example.invalid"', DeliverableEmailAddress::UNKNOWN_TOP_LEVEL_DOMAIN_ERROR];
        yield 'a denied domain' => ['ada@spam.example', new DeliverableEmailAddress(deniedDomains: ['spam.example'], deniedDomainMessage: 'denied {{ domain }}'), 'denied {{ domain }}', '"spam.example"', DeliverableEmailAddress::DENIED_DOMAIN_ERROR];
        yield 'a domain not allowed' => ['ada@example.com', new DeliverableEmailAddress(allowedDomains: ['corp.example'], domainNotAllowedMessage: 'not allowed {{ domain }}'), 'not allowed {{ domain }}', '"example.com"', DeliverableEmailAddress::DOMAIN_NOT_ALLOWED_ERROR];
        yield 'a disposable domain' => ['ada@mailinator.com', new DeliverableEmailAddress(disposableMessage: 'disposable'), 'disposable', '"mailinator.com"', DeliverableEmailAddress::DISPOSABLE_ERROR];
        yield 'no mail server' => ['ada@example.org', new DeliverableEmailAddress(checkMailServer: true, noMailServerMessage: 'none {{ domain }}'), 'none {{ domain }}', '"example.org"', DeliverableEmailAddress::NO_MAIL_SERVER_ERROR];
        yield 'a null MX' => ['ada@example.net', new DeliverableEmailAddress(checkMailServer: true, refusesMailMessage: 'refuses {{ domain }}'), 'refuses {{ domain }}', '"example.net"', DeliverableEmailAddress::REFUSES_MAIL_ERROR];
    }

    #[DataProvider('refusals')]
    public function testNamesTheFirstProblem(string $value, DeliverableEmailAddress $constraint, string $message, string $domain, string $code): void
    {
        $this->validator->validateInContext($value, $constraint, $this->context);

        $this->buildViolation($message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ domain }}', $domain)
            ->setParameter('{{ reason }}', '')
            ->setParameter('{{ suggestion }}', '')
            ->setCode($code)
            ->assertRaised();
    }

    public function testDefaultMessages(): void
    {
        $constraint = new DeliverableEmailAddress();

        self::assertSame(['This value is not a valid email address.', DeliverableEmailAddress::SYNTAX_ERROR], $constraint->violationOf(Problem::Syntax));
        self::assertSame(['The domain {{ domain }} does not end with a known top-level domain.', DeliverableEmailAddress::UNKNOWN_TOP_LEVEL_DOMAIN_ERROR], $constraint->violationOf(Problem::UnknownTopLevelDomain));
        self::assertSame(['Addresses at {{ domain }} are not accepted.', DeliverableEmailAddress::DENIED_DOMAIN_ERROR], $constraint->violationOf(Problem::DeniedDomain));
        self::assertSame(['Addresses at {{ domain }} are not accepted here.', DeliverableEmailAddress::DOMAIN_NOT_ALLOWED_ERROR], $constraint->violationOf(Problem::DomainNotAllowed));
        self::assertSame(['Disposable email addresses are not accepted.', DeliverableEmailAddress::DISPOSABLE_ERROR], $constraint->violationOf(Problem::Disposable));
        self::assertSame(['No mail server receives mail for {{ domain }}.', DeliverableEmailAddress::NO_MAIL_SERVER_ERROR], $constraint->violationOf(Problem::NoMailServer));
        self::assertSame(['The domain {{ domain }} receives no mail.', DeliverableEmailAddress::REFUSES_MAIL_ERROR], $constraint->violationOf(Problem::RefusesMail));
        self::assertSame('SYNTAX_ERROR', DeliverableEmailAddress::getErrorName(DeliverableEmailAddress::SYNTAX_ERROR));
        self::assertSame('REFUSES_MAIL_ERROR', DeliverableEmailAddress::getErrorName(DeliverableEmailAddress::REFUSES_MAIL_ERROR));
        self::assertSame(DeliverableEmailAddress::PROPERTY_CONSTRAINT, $constraint->getTargets());
        self::assertFalse($constraint->allowDisposable);
        self::assertTrue($constraint->knownTopLevelDomain);
        self::assertFalse($constraint->checkMailServer);
    }

    public function testFollowsTheSyntaxOptions(): void
    {
        $constraint = new DeliverableEmailAddress(internationalLocalPart: true, quotedLocalPart: true, ipLiteral: true);

        $this->validator->validateInContext('ädä@example.com', $constraint, $this->context);
        $this->validator->validateInContext('"a b"@example.com', $constraint, $this->context);
        $this->validator->validateInContext('ada@[192.0.2.1]', $constraint, $this->context);

        $this->assertNoViolation();
    }

    public function testRefusesTheInternationalFormsByDefault(): void
    {
        foreach (['ädä@example.com', '"a b"@example.com', 'ada@[192.0.2.1]'] as $value) {
            $this->validator->validateInContext($value, new DeliverableEmailAddress(), $this->context);
        }

        self::assertCount(3, $this->context->getViolations());
    }

    public function testAllowsDisposableDomainsAndUnknownTopLevelDomainsWhenAsked(): void
    {
        $this->validator->validateInContext('ada@mailinator.com', new DeliverableEmailAddress(allowDisposable: true), $this->context);
        $this->validator->validateInContext('ada@example.invalid', new DeliverableEmailAddress(knownTopLevelDomain: false), $this->context);

        $this->assertNoViolation();
    }

    public function testAsksTheDnsOnlyWhenTheConstraintSaysSo(): void
    {
        $this->validator->validateInContext('ada@example.org', new DeliverableEmailAddress(), $this->context);

        $this->assertNoViolation();
    }

    public function testWorksWithTheValidatorBuiltWithoutAContainer(): void
    {
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate('ada@example.com', new DeliverableEmailAddress(knownTopLevelDomain: false)));
        self::assertCount(1, $validator->validate('ada@@example.com', new DeliverableEmailAddress()));
        self::assertCount(0, $validator->validate('ada@example.com', new DeliverableEmailAddress()), 'The bundled lists are read.');
    }
}
