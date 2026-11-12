<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit;

use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\DomainRules;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddress\Problem;
use Phil\EmailAddress\SyntaxError;
use Phil\EmailAddress\SyntaxPolicy;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\Validation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailValidator::class)]
#[CoversClass(Validation::class)]
#[CoversClass(MailDomain::class)]
#[CoversClass(StaticResolver::class)]
final class EmailValidatorTest extends TestCase
{
    public function testAcceptsAnAddressWithoutAskingTheDnsByDefault(): void
    {
        $validation = new EmailValidator()->validate('ada@example.com');

        self::assertTrue($validation->isValid());
        self::assertSame('ada@example.com', $validation->address?->toString());
        self::assertNull($validation->syntaxError);
        self::assertSame([], $validation->problems);
        self::assertNull($validation->suggestion);
        self::assertNull($validation->mailDomain);
    }

    public function testReportsTheSyntaxErrorAndStillSuggests(): void
    {
        $validation = new EmailValidator()->validate('ada@gmailcom');

        self::assertFalse($validation->isValid());
        self::assertNull($validation->address);
        self::assertSame(SyntaxError::SingleLabelDomain, $validation->syntaxError);
        self::assertSame([Problem::Syntax], $validation->problems);
        self::assertTrue($validation->has(Problem::Syntax));
        self::assertFalse($validation->has(Problem::Disposable));
        self::assertSame('ada@gmail.com', $validation->suggestion?->toString());
    }

    public function testUsesThePolicy(): void
    {
        $validator = new EmailValidator(new SyntaxPolicy()->withQuotedLocalPart());

        self::assertTrue($validator->validate('"ada"@example.com')->isValid());
    }

    public function testRefusesByTheDomainRulesBeforeTheDisposableDomains(): void
    {
        $validator = new EmailValidator(
            rules: DomainRules::of(denied: ['mailinator.com']),
            disposable: DisposableDomains::of(['mailinator.com', 'yopmail.com']),
            resolver: $this->resolverNeverAsked(),
        );

        self::assertSame([Problem::DeniedDomain], $validator->validate('ada@mailinator.com')->problems);
        self::assertSame([Problem::Disposable], $validator->validate('ada@yopmail.com')->problems);
    }

    public function testRefusesAnUnknownTopLevelDomainBeforeTheDomainRules(): void
    {
        $validator = new EmailValidator(
            new SyntaxPolicy()->withIpLiteral(),
            rules: DomainRules::of(denied: ['example.invalid', 'example.com']),
            resolver: $this->resolverNeverAsked(),
            topLevelDomains: TopLevelDomains::of(['com', 'ca']),
        );

        $unknown = $validator->validate('ada@example.invalid');
        self::assertSame([Problem::UnknownTopLevelDomain], $unknown->problems);
        self::assertSame('ada@example.invalid', $unknown->address?->toString());
        self::assertSame([Problem::DeniedDomain], $validator->validate('ada@example.com')->problems);
        self::assertSame([Problem::UnknownTopLevelDomain], $validator->validate('ada@example.co')->problems);
        self::assertTrue($validator->validate('ada@[192.0.2.1]')->isValid());
    }

    public function testAnEmptyListOfTopLevelDomainsChecksNothing(): void
    {
        $validator = new EmailValidator(topLevelDomains: TopLevelDomains::of([]));

        self::assertTrue($validator->validate('ada@example.invalid')->isValid());
    }

    public function testAsksTheDnsLast(): void
    {
        $validator = new EmailValidator(resolver: new StaticResolver([
            'example.com' => MailDomain::Mx,
            'example.net' => MailDomain::NullMx,
            'example.org' => MailDomain::NoRecord,
            'example.edu' => MailDomain::AddressOnly,
        ]));

        $mx = $validator->validate('ada@example.com');
        self::assertSame([], $mx->problems);
        self::assertSame(MailDomain::Mx, $mx->mailDomain);
        self::assertSame([Problem::RefusesMail], $validator->validate('ada@example.net')->problems);
        self::assertSame([Problem::NoMailServer], $validator->validate('ada@example.org')->problems);
        self::assertSame([], $validator->validate('ada@example.edu')->problems);

        $unknown = $validator->validate('ada@example.info');
        self::assertSame([], $unknown->problems);
        self::assertSame(MailDomain::Unknown, $unknown->mailDomain);
    }

    public function testDoesNotAskTheDnsForAnIpLiteral(): void
    {
        $validator = new EmailValidator(new SyntaxPolicy()->withIpLiteral(), resolver: $this->resolverNeverAsked());

        self::assertTrue($validator->validate('ada@[192.0.2.1]')->isValid());
    }

    public function testSuggestsForAValidAddress(): void
    {
        $validator = new EmailValidator(resolver: new StaticResolver(otherwise: MailDomain::NoRecord));
        $validation = $validator->validate('ada@gmial.com');

        self::assertSame([Problem::NoMailServer], $validation->problems);
        self::assertSame('ada@gmail.com', $validation->suggestion?->toString());
        self::assertNull(new EmailValidator(suggester: null)->validate('ada@gmial.com')->suggestion);
    }

    public function testSaysWhetherEachAnswerOfTheDnsAcceptsMail(): void
    {
        self::assertTrue(MailDomain::Mx->acceptsMail());
        self::assertTrue(MailDomain::AddressOnly->acceptsMail());
        self::assertFalse(MailDomain::NullMx->acceptsMail());
        self::assertFalse(MailDomain::NoRecord->acceptsMail());
        self::assertNull(MailDomain::Unknown->acceptsMail());
    }

    private function resolverNeverAsked(): MailDomainResolver
    {
        $resolver = $this->createMock(MailDomainResolver::class);
        $resolver->expects(self::never())->method('resolve');

        return $resolver;
    }
}
