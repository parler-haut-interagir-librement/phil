<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\Validator\DeliverableEmailAddressValidator;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The validators of the bundle and of the library, built as the container builds them: known lists, and a resolver
 * that knows `example.org` receives mail and `nomail.example` none.
 */
final class ValidatorFactory extends ConstraintValidatorFactory
{
    public function __construct(
        private readonly EmailPolicies $policies,
    ) {
        parent::__construct();
    }

    public static function validator(EmailPolicies $policies): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->addMethodMapping('loadValidatorMetadata')->setConstraintValidatorFactory(new self($policies))->getValidator();
    }

    #[Override]
    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        return match ($constraint->validatedBy()) {
            AcceptedEmailAddressValidator::class => new AcceptedEmailAddressValidator($this->policies),
            DeliverableEmailAddressValidator::class => new DeliverableEmailAddressValidator(
                DisposableDomains::of(['mailinator.com']),
                TopLevelDomains::of(['org', 'com', 'example']),
                new StaticResolver(['example.org' => MailDomain::Mx, 'nomail.example' => MailDomain::NoRecord]),
            ),
            default => parent::getInstance($constraint),
        };
    }
}
