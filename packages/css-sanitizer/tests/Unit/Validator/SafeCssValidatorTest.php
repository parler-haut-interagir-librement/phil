<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit\Validator;

use Override;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Validator\SafeCss;
use Phil\CssSanitizer\Validator\SafeCssValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<SafeCssValidator>
 */
#[CoversClass(SafeCss::class)]
#[CoversClass(SafeCssValidator::class)]
final class SafeCssValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new SafeCssValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validate(null, new SafeCss());
        $this->validate('', new SafeCss());

        $this->assertNoViolation();
    }

    public function testCleanCssIsValid(): void
    {
        $this->validate('a { color: red }', new SafeCss());

        $this->assertNoViolation();
    }

    public function testEachRemovalIsAViolation(): void
    {
        $this->validate("@import \"x.css\";\na { position: fixed; color: red }", new SafeCss());

        $this->buildViolation('This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.')
            ->setParameter('{{ removed }}', 'an @import rule')
            ->setParameter('{{ subject }}', '@import')
            ->setParameter('{{ line }}', '1')
            ->setParameter('{{ reason }}', 'import')
            ->setCode(SafeCss::NOT_ALLOWED_ERROR)
            ->buildNextViolation('This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.')
            ->setParameter('{{ removed }}', 'a property that is not allowed')
            ->setParameter('{{ subject }}', 'position')
            ->setParameter('{{ line }}', '2')
            ->setParameter('{{ reason }}', 'property')
            ->setCode(SafeCss::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    /**
     * A source of a megabyte can hold hundreds of thousands of removals: the first hundred are violations, and one
     * more counts the others.
     */
    public function testTheRemovalsPastTheFirstHundredAreOneViolation(): void
    {
        $this->validate(str_repeat('a{x:1}', 103), new SafeCss(moreMessage: 'And {{ count }} more.'));

        $violations = $this->context->getViolations();
        self::assertCount(101, $violations);
        self::assertSame('property', $violations[99]?->getParameters()['{{ reason }}']);
        $more = $violations[100];
        self::assertNotNull($more);
        self::assertSame('And {{ count }} more.', $more->getMessageTemplate());
        self::assertSame(['{{ count }}' => '3'], $more->getParameters());
        self::assertSame(3, $more->getPlural());
        self::assertSame(SafeCss::NOT_ALLOWED_ERROR, $more->getCode());
    }

    public function testExactlyAHundredRemovalsAreAHundredViolations(): void
    {
        $this->validate(str_repeat('a{x:1}', 100), new SafeCss());

        self::assertCount(100, $this->context->getViolations());
    }

    public function testDeclarationsAreCheckedAsAStyleAttribute(): void
    {
        $this->validate(new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 'color: red; behavior: url(x)';
            }
        }, new SafeCss(declarations: true, message: 'Not allowed: {{ reason }}.'));

        $this->buildViolation('Not allowed: {{ reason }}.')
            ->setParameter('{{ removed }}', 'a property that runs code')
            ->setParameter('{{ subject }}', 'behavior')
            ->setParameter('{{ line }}', '1')
            ->setParameter('{{ reason }}', 'denied_property')
            ->setCode(SafeCss::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testAStyleSheetIsNotADeclarationList(): void
    {
        $this->validate('color: red', new SafeCss());

        $this->buildViolation('This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.')
            ->setParameter('{{ removed }}', 'text that is not valid CSS')
            ->setParameter('{{ subject }}', 'color: red')
            ->setParameter('{{ line }}', '1')
            ->setParameter('{{ reason }}', 'syntax')
            ->setCode(SafeCss::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testTheSanitizerCarriesThePolicy(): void
    {
        $validator = new SafeCssValidator(new CssSanitizer(new Policy()->withProperties('position')));
        $validator->validateInContext('a { position: fixed }', new SafeCss(), $this->context);

        $this->assertNoViolation();
    }

    public function testItRefusesOtherConstraints(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('a {}', new NotBlank());
    }

    public function testItRefusesValuesThatAreNotStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate(new stdClass(), new SafeCss());
    }

    public function testTheConstraintHasDefaults(): void
    {
        $constraint = new SafeCss(groups: ['strict'], payload: 'p');

        self::assertFalse($constraint->declarations);
        self::assertSame('This CSS holds {{ count }} more parts that are not allowed.', $constraint->moreMessage);
        self::assertSame(['strict'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
        self::assertSame('NOT_ALLOWED_ERROR', SafeCss::getErrorName(SafeCss::NOT_ALLOWED_ERROR));
    }
}
