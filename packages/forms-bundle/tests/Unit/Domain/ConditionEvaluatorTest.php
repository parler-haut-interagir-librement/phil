<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Domain\ValueObject\Notification;
use Phil\FormsBundle\Tests\Unit\Fixtures\RecordingEvaluator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The evaluator an application gives a form decides of the conditions of its notifications, and of whether a
 * condition that reads a field not asked holds.
 */
#[CoversClass(Form::class)]
#[CoversClass(Notification::class)]
final class ConditionEvaluatorTest extends TestCase
{
    public function testTheConditionOfANotificationIsCheckedByTheEvaluatorOfTheApplication(): void
    {
        $evaluator = new RecordingEvaluator();
        $form = Form::create('registration', 'Registration', [
            Field::of('name', 'Name', FieldType::Text),
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
        ]);

        $form->notifyAlso([Notification::to(['blue@example.com'], when: ['own' => 'grammar'])], $evaluator);

        self::assertSame([[['own' => 'grammar'], ['name', 'team']]], $evaluator->checked);
    }

    public function testAConditionThatReadsAFieldNotAskedDoesNotHoldWhateverTheEvaluatorSays(): void
    {
        $form = Form::create('registration', 'Registration', [Field::of('name', 'Name', FieldType::Text)]);
        $evaluator = new RecordingEvaluator();

        self::assertFalse($form->holds(['own' => 'grammar'], ['name' => 'Ada'], $evaluator), 'the team is not asked');
        self::assertTrue($form->holds(['own' => 'grammar'], ['name' => 'Ada', 'team' => 'Blue'], $evaluator));
        self::assertTrue($form->holds(null, [], $evaluator));
    }

    public function testTheRecipientsOfANotificationAreEachGivenOnceAsAList(): void
    {
        self::assertSame(['a@example.com', 'b@example.com'], Notification::to(['a@example.com', 'a@example.com', 'b@example.com'])->recipients);
    }
}
