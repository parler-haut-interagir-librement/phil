<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessengerBundle\Infrastructure\Validator\HandleOfType;
use Phil\InstantMessengerBundle\Infrastructure\Validator\HandleOfTypeValidator;
use Phil\InstantMessengerBundle\Tests\Unit\Fixtures\ForumMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<HandleOfTypeValidator>
 */
#[CoversClass(HandleOfType::class)]
#[CoversClass(HandleOfTypeValidator::class)]
final class HandleOfTypeValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): HandleOfTypeValidator
    {
        return new HandleOfTypeValidator();
    }

    public function testNothingIsValid(): void
    {
        $this->validate(null, new HandleOfType());
        $this->validate('', new HandleOfType());

        $this->assertNoViolation();
    }

    public function testAHandleOnAnAcceptedServiceIsValid(): void
    {
        $constraint = new HandleOfType([KnownMessenger::Signal, KnownMessenger::WhatsApp]);

        $this->validate(Handle::of(KnownMessenger::Signal, '+15145550123'), $constraint);
        $this->validate('https://wa.me/15145550123', $constraint);
        $this->validate('forum:ada', new HandleOfType(ForumMessenger::class));
        $this->validate('https://wa.me/15145550123', new HandleOfType(EnumSet::of(KnownMessenger::class, KnownMessenger::WhatsApp)));
        $this->validate('forum:ada', new HandleOfType([EnumSet::all(ForumMessenger::class), KnownMessenger::Signal]));

        $this->assertNoViolation();
    }

    public function testAHandleOnAnotherServiceIsNot(): void
    {
        $this->validate(Handle::of(KnownMessenger::Telegram, 'adalove'), new HandleOfType([KnownMessenger::Signal, ForumMessenger::Forum]));

        $this->buildViolation('This handle is on none of the accepted services: {{ types }}.')
            ->setParameter('{{ types }}', 'Signal, Forum')
            ->setCode(HandleOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testAHandleOfAnotherEnumIsNot(): void
    {
        $this->validate(Handle::of(ForumMessenger::Forum, 'forum:ada'), new HandleOfType(KnownMessenger::Telegram, 'Not on {{ types }}.'));

        $this->buildViolation('Not on {{ types }}.')
            ->setParameter('{{ types }}', 'Telegram')
            ->setCode(HandleOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testATextOnNoAcceptedServiceIsNot(): void
    {
        $this->validate('514 555-0123', new HandleOfType(KnownMessenger::WhatsApp));

        $this->buildViolation('This handle is on none of the accepted services: {{ types }}.')
            ->setParameter('{{ types }}', 'WhatsApp')
            ->setCode(HandleOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintHasItsDefaults(): void
    {
        $constraint = new HandleOfType(groups: ['contact']);

        self::assertSame([null, 'This handle is on none of the accepted services: {{ types }}.', ['contact']], [$constraint->types, $constraint->message, $constraint->groups]);
        self::assertSame('NOT_ACCEPTED_ERROR', HandleOfType::getErrorName(HandleOfType::NOT_ACCEPTED_ERROR));
    }

    public function testOnlyItsConstraintAndValuesAreTaken(): void
    {
        try {
            $this->validate('adalove', new NotBlank());
            self::fail('Another constraint was taken.');
        } catch (UnexpectedTypeException) {
        }

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Expected argument of type "string|Phil\\InstantMessenger\\Handle", "stdClass" given');

        $this->validate(new stdClass(), new HandleOfType());
    }
}
