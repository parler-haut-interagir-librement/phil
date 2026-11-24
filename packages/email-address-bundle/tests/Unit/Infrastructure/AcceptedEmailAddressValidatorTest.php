<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EmailAddress\Validator\DeliverableEmailAddress;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Application\EmailPolicy;
use Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator;
use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[CoversClass(AcceptedEmailAddress::class)]
#[CoversClass(AcceptedEmailAddressValidator::class)]
final class AcceptedEmailAddressValidatorTest extends TestCase
{
    public function testTheBundlePolicyApplies(): void
    {
        $validator = ValidatorFactory::validator(new EmailPolicies(new EmailPolicy(deniedDomains: ['spam.example'])));

        self::assertSame([], $this->codes($validator->validate('ada@example.org', new AcceptedEmailAddress())));
        self::assertSame([DeliverableEmailAddress::DENIED_DOMAIN_ERROR], $this->codes($validator->validate('ada@spam.example', new AcceptedEmailAddress())));
        self::assertSame([DeliverableEmailAddress::DISPOSABLE_ERROR], $this->codes($validator->validate('ada@mailinator.com', new AcceptedEmailAddress())));
        self::assertSame([DeliverableEmailAddress::SYNTAX_ERROR], $this->codes($validator->validate('ada@', new AcceptedEmailAddress())));
        self::assertSame([], $this->codes($validator->validate('ada@nomail.example', new AcceptedEmailAddress())));
        self::assertSame([], $this->codes($validator->validate(null, new AcceptedEmailAddress())));
        self::assertSame([], $this->codes($validator->validate('', new AcceptedEmailAddress())));
    }

    public function testTheListPolicyIsLaidOverTheBundle(): void
    {
        $validator = ValidatorFactory::validator(new EmailPolicies(
            new EmailPolicy(deniedDomains: ['spam.example']),
            lists: ['staff' => new EmailPolicy(checkMailServer: true, allowedDomains: ['example.org', 'nomail.example'])],
        ));

        self::assertSame([], $this->codes($validator->validate('ada@example.org', new AcceptedEmailAddress('staff'))));
        self::assertSame([DeliverableEmailAddress::DOMAIN_NOT_ALLOWED_ERROR], $this->codes($validator->validate('ada@example.com', new AcceptedEmailAddress('staff'))));
        self::assertSame([DeliverableEmailAddress::NO_MAIL_SERVER_ERROR], $this->codes($validator->validate('ada@nomail.example', new AcceptedEmailAddress('staff'))));
        self::assertSame([DeliverableEmailAddress::DENIED_DOMAIN_ERROR], $this->codes($validator->validate('ada@spam.example', new AcceptedEmailAddress('staff'))));
    }

    public function testTheViolationLandsOnThePropertyInTheGroupValidated(): void
    {
        $validator = ValidatorFactory::validator(new EmailPolicies(new EmailPolicy(deniedDomains: ['spam.example'])));
        $subscriber = new Subscriber('ada@spam.example');

        $violations = $validator->validate($subscriber, null, ['signup']);

        self::assertCount(1, $violations);
        self::assertSame('email', $violations->get(0)->getPropertyPath());
        self::assertSame('Addresses at "spam.example" are not accepted.', $violations->get(0)->getMessage());
        self::assertSame($subscriber, $violations->get(0)->getRoot());
        self::assertCount(0, $validator->validate($subscriber, null, ['other']));
        self::assertCount(1, $validator->validate(new Subscriber(''), null, ['signup', 'Default']));
    }

    public function testTheConstraintTargetsProperties(): void
    {
        self::assertSame(Constraint::PROPERTY_CONSTRAINT, new AcceptedEmailAddress()->getTargets());
        self::assertSame(['signup'], new AcceptedEmailAddress(groups: ['signup'])->groups);
        self::assertSame('payload', new AcceptedEmailAddress(payload: 'payload')->payload);
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        ValidatorFactory::validator(new EmailPolicies())->validate('ada@example.org', new class extends NotBlank {
            #[Override]
            public function validatedBy(): string
            {
                return AcceptedEmailAddressValidator::class;
            }
        });
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     *
     * @return list<string|null>
     */
    private function codes(iterable $violations): array
    {
        $codes = [];

        foreach ($violations as $violation) {
            $codes[] = $violation->getCode();
        }

        return $codes;
    }
}
