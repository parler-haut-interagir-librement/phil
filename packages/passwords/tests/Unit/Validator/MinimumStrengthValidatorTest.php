<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Validator;

use Override;
use Phil\Passwords\Contract\PasswordUserInputs;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\StrengthEstimator;
use Phil\Passwords\Validator\MinimumStrength;
use Phil\Passwords\Validator\MinimumStrengthValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MinimumStrengthValidator>
 */
#[CoversClass(MinimumStrength::class)]
#[CoversClass(MinimumStrengthValidator::class)]
final class MinimumStrengthValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullAndEmptyAreValid(): void
    {
        $this->validate(null, new MinimumStrength());
        $this->validate('', new MinimumStrength());

        $this->assertNoViolation();
    }

    public function testAStrongPasswordIsValid(): void
    {
        $this->validate('correct-horse-battery-staple-unicorn', new MinimumStrength());

        $this->assertNoViolation();
    }

    /**
     * ASVS 5.0, 6.2.9: a password of 200 characters is accepted, and analysed over its first 128 in bounded time.
     */
    public function testAPasswordOf200CharactersIsAccepted(): void
    {
        $password = substr(str_repeat('Lighthouse-keepers drink 7 teas at dusk; ', 5), 0, 200);
        self::assertSame(200, \strlen($password));

        $this->validate($password, new MinimumStrength());

        $this->assertNoViolation();
        $strength = new StrengthEstimator()->estimate($password);
        self::assertSame([200, StrengthEstimator::MAX_LENGTH], [$strength->length, $strength->analysedLength]);
        self::assertTrue($strength->isTruncated());
    }

    public function testAStringableIsRead(): void
    {
        $this->validate(new class implements Stringable {
            public function __toString(): string
            {
                return 'Tr0ub4dor&3-xylophone';
            }
        }, new MinimumStrength());

        $this->assertNoViolation();
    }

    public function testAWeakPasswordIsAViolationWithItsFeedback(): void
    {
        $this->validate('password', new MinimumStrength());

        $this->buildViolation('This password is too easy to guess.')
            ->setParameter('{{ score }}', '0')
            ->setParameter('{{ min_score }}', '3')
            ->setParameter('{{ warning }}', 'warning.top_ten')
            ->setParameter('{{ suggestion }}', 'suggestion.add_word')
            ->setCode(MinimumStrength::TOO_WEAK_ERROR)
            ->setInvalidValue('')
            ->assertRaised();

        // The score and the minimum are integers, which the comparison of the violations above does not tell.
        $parameters = $this->context->getViolations()[0]?->getParameters() ?? [];
        self::assertSame(0, $parameters['{{ score }}'] ?? null);
        self::assertSame(3, $parameters['{{ min_score }}'] ?? null);
    }

    public function testTheMinimumAndTheMessage(): void
    {
        $this->validate('14/03/1987', new MinimumStrength(Score::Weak));
        $this->assertNoViolation();

        $this->validate('x7#Kq!2m', new MinimumStrength(Score::VeryStrong, 'Too weak.'));

        $this->buildViolation('Too weak.')
            ->setParameter('{{ score }}', '3')
            ->setParameter('{{ min_score }}', '4')
            ->setParameter('{{ warning }}', '')
            ->setParameter('{{ suggestion }}', '')
            ->setCode(MinimumStrength::TOO_WEAK_ERROR)
            ->setInvalidValue('')
            ->assertRaised();
    }

    public function testTheUserInputsOfTheObject(): void
    {
        $this->setObject(new class implements PasswordUserInputs {
            #[Override]
            public function passwordUserInputs(): array
            {
                return ['zorglubien'];
            }
        });

        $this->validate('zorglubien', new MinimumStrength(Score::Weak));

        $this->buildViolation('This password is too easy to guess.')
            ->setParameter('{{ score }}', '0')
            ->setParameter('{{ min_score }}', '1')
            ->setParameter('{{ warning }}', 'warning.user_input')
            ->setParameter('{{ suggestion }}', 'suggestion.add_word')
            ->setCode(MinimumStrength::TOO_WEAK_ERROR)
            ->setInvalidValue('')
            ->assertRaised();
    }

    public function testAnotherObjectGivesNoInput(): void
    {
        $this->setObject(new stdClass());
        $this->validate('zorglubien', new MinimumStrength(Score::Weak));

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

        $this->validate(42, new MinimumStrength());
    }

    public function testTheErrorName(): void
    {
        self::assertSame('TOO_WEAK_ERROR', MinimumStrength::getErrorName(MinimumStrength::TOO_WEAK_ERROR));
        self::assertSame(['Default'], new MinimumStrength()->groups);
        self::assertSame(['strict'], new MinimumStrength(groups: ['strict'], payload: 'p')->groups);
        self::assertSame('p', new MinimumStrength(payload: 'p')->payload);
    }

    public function testTheValidatorsOwnEstimator(): void
    {
        $validator = new MinimumStrengthValidator(new StrengthEstimator([], [], 2026));
        $validator->validateInContext('password', new MinimumStrength(Score::Weak), $this->context);

        $this->assertNoViolation();
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new MinimumStrengthValidator();
    }
}
