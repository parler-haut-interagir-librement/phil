<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Validator;

use Override;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Breach\Contract\BreachedPasswordSource;
use Phil\Passwords\Breach\InMemoryBreachSource;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use Phil\Passwords\Validator\NotCompromised;
use Phil\Passwords\Validator\NotCompromisedValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use SensitiveParameter;
use Stringable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NotCompromisedValidator>
 */
#[CoversClass(NotCompromised::class)]
#[CoversClass(NotCompromisedValidator::class)]
final class NotCompromisedValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullAndEmptyAreValid(): void
    {
        $this->validate(null, new NotCompromised());
        $this->validate('', new NotCompromised());

        $this->assertNoViolation();
    }

    public function testAPasswordInNoBreachIsValid(): void
    {
        $this->validate('correct horse', new NotCompromised());

        $this->assertNoViolation();
    }

    public function testACompromisedPassword(): void
    {
        $this->validate(new class implements Stringable {
            public function __toString(): string
            {
                return 'password';
            }
        }, new NotCompromised());

        $this->buildViolation('This password appears in known data breaches: choose another one.')
            ->setParameter('{{ occurrences }}', '3')
            ->setCode(NotCompromised::COMPROMISED_ERROR)
            ->setInvalidValue('')
            ->assertRaised();
    }

    public function testTheThreshold(): void
    {
        $this->validate('password', new NotCompromised(4));

        $this->assertNoViolation();
    }

    public function testAnUnavailableSourceFailsClosed(): void
    {
        $validator = new NotCompromisedValidator(new BreachChecker($this->failing()));
        $validator->validateInContext('password', new NotCompromised(unavailableMessage: 'Later.'), $this->context);

        $this->buildViolation('Later.')
            ->setCode(NotCompromised::UNAVAILABLE_ERROR)
            ->setInvalidValue('')
            ->assertRaised();
    }

    public function testAnUnavailableSourceSkipped(): void
    {
        $validator = new NotCompromisedValidator(new BreachChecker($this->failing()));
        $validator->validateInContext('password', new NotCompromised(skipOnError: true), $this->context);

        $this->assertNoViolation();
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('x', new NotBlank());
    }

    public function testANonStringIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate(['password'], new NotCompromised());
    }

    public function testTheErrorNamesAndOptions(): void
    {
        self::assertSame('COMPROMISED_ERROR', NotCompromised::getErrorName(NotCompromised::COMPROMISED_ERROR));
        self::assertSame('UNAVAILABLE_ERROR', NotCompromised::getErrorName(NotCompromised::UNAVAILABLE_ERROR));

        $constraint = new NotCompromised(groups: ['signup'], payload: 'p');
        self::assertSame(['signup'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
        self::assertSame(1, $constraint->threshold);
        self::assertFalse($constraint->skipOnError);
    }

    public function testTheTranslations(): void
    {
        $constraint = new NotCompromised();
        $directory = \dirname(__DIR__, 3).'/translations/';

        foreach (['en', 'fr'] as $locale) {
            /** @var array<string, string> $messages */
            $messages = require $directory.'validators.'.$locale.'.php';

            self::assertSame(
                ['This password is too easy to guess.', $constraint->message, $constraint->unavailableMessage],
                array_keys($messages),
            );
        }
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotCompromisedValidator(new BreachChecker(InMemoryBreachSource::fromPasswords(['password' => 3])));
    }

    private function failing(): BreachedPasswordSource
    {
        return new class implements BreachedPasswordSource {
            #[Override]
            public function occurrences(#[SensitiveParameter] string $password): int
            {
                throw BreachCheckUnavailable::malformed();
            }
        };
    }
}
