<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Override;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Domain\ValueObject\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

/**
 * The questions a form asks by conditions and tiers, the notifications it sends, and the values it seals.
 */
#[CoversClass(Form::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Notification::class)]
final class FormLogicTest extends TestCase
{
    private function registration(): Form
    {
        return Form::create('registration', 'Registration', [
            Field::of('name', 'Name', FieldType::Text, true),
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('captain', 'Captain of Blue?', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]),
            Field::of('armband', 'Armband size', FieldType::Text, true, shownWhen: ['==' => [['var' => 'captain'], true]]),
            Field::of('meal', 'Meal', FieldType::Choice, true, choices: ['Vegan', 'Other'], tiers: ['vip']),
            Field::of('health', 'Health', FieldType::Textarea, sensitive: true),
        ]);
    }

    public function testAFieldIsAskedWhenItsConditionHoldsAndForItsTiers(): void
    {
        $form = $this->registration();

        self::assertSame(['name' => 'Ada', 'team' => 'Green', 'health' => null], $form->accept(['name' => 'Ada', 'team' => 'Green', 'captain' => '1', 'armband' => 'L', 'meal' => 'Vegan']), 'what is not asked is not kept');
        self::assertSame(['name' => 'Ada', 'team' => 'Blue', 'captain' => false, 'health' => null], $form->accept(['name' => 'Ada', 'team' => 'Blue', 'armband' => 'L']));
        self::assertSame(['name' => 'Ada', 'team' => 'Blue', 'captain' => true, 'armband' => 'L', 'health' => null], $form->accept(['name' => 'Ada', 'team' => 'Blue', 'captain' => '1', 'armband' => 'L']));
        self::assertSame(['name' => 'Ada', 'team' => null, 'meal' => 'Vegan', 'health' => null], $form->accept(['name' => 'Ada', 'meal' => 'Vegan', 'captain' => '1'], tiers: ['vip']));
        self::assertSame(['name', 'team', 'captain', 'armband', 'health'], array_map(static fn (Field $field): string => $field->name, $form->fieldsFor([])));
        self::assertSame(['name', 'team', 'captain', 'armband', 'meal', 'health'], array_map(static fn (Field $field): string => $field->name, $form->fieldsFor(['vip', 'x'])));

        try {
            $form->accept(['team' => 'Blue', 'captain' => '1'], tiers: ['vip']);
            self::fail('refused');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame(['name' => 'required', 'armband' => 'required', 'meal' => 'required'], $invalidEntry->errors);
        }
    }

    public function testAConditionThatReadsAFieldNotAskedDoesNotHold(): void
    {
        $form = $this->registration();
        $condition = ['==' => [['var' => 'captain'], false]];

        self::assertSame([true, false, true, false], [
            $form->holds(null, []),
            $form->holds($condition, ['team' => 'Green']),
            $form->holds($condition, ['captain' => false]),
            $form->holds(['in' => [['var' => 'team'], ['Blue']]], ['team' => 'Green']),
        ]);
        $form->define([
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: ['!=' => [['var' => 'team'], 'Blue']]),
        ]);
        self::assertSame(['team' => null, 'nickname' => 'Ace'], $form->accept(['nickname' => 'Ace']), 'a field left empty is not "Blue", as JsonLogic reads it');
        self::assertSame(['team' => 'Blue'], $form->accept(['team' => 'Blue', 'nickname' => 'Ace']));
        $form->define([
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green'], tiers: ['vip']),
            Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: ['!=' => [['var' => 'team'], 'Blue']]),
        ]);
        self::assertSame([], $form->accept(['nickname' => 'Ace']), 'a field not asked satisfies no condition, not even "!="');
    }

    public function testAFieldsConditionIsCheckedAgainstTheFieldsBeforeIt(): void
    {
        $form = $this->registration();

        foreach ([
            [[Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]), Field::of('team', 'Team', FieldType::Choice, choices: ['Blue'])], 'A condition reads "team", which is not a field asked before it.'],
            [[Field::of('team', 'Team', FieldType::Choice, choices: ['Blue'], shownWhen: ['==' => [['var' => 'team'], 'Blue']])], 'A condition reads "team", which is not a field asked before it.'],
            [[Field::of('team', 'Team', FieldType::Choice, choices: ['Blue']), Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Red']])], 'A condition compares "team" with a value it never takes.'],
        ] as [$fields, $message]) {
            try {
                $form->define($fields);
                self::fail($message);
            } catch (InvalidForm $invalidForm) {
                self::assertSame($message, $invalidForm->getMessage());
            }
        }

        $evaluator = new class implements ConditionEvaluator {
            /** @var list<array<array-key, mixed>> */
            public array $checked = [];

            #[Override]
            public function fields(array $condition): array
            {
                return [];
            }

            #[Override]
            public function check(array $condition, array $fields): void
            {
                $this->checked[] = [$condition, array_keys($fields)];
            }

            #[Override]
            public function holds(array $condition, array $values): bool
            {
                return [] === $values;
            }
        };
        $form->define([Field::of('a', 'A', FieldType::Text, shownWhen: ['any' => 1]), Field::of('b', 'B', FieldType::Text, shownWhen: ['other' => 2])], $evaluator);
        self::assertSame([[['any' => 1], []], [['other' => 2], ['a']]], $evaluator->checked);
        self::assertSame(['a' => 'x'], $form->accept(['a' => 'x', 'b' => 'y'], logic: $evaluator), 'the evaluator of the application decides');
        self::assertTrue($form->holds(['any' => 1], [], $evaluator));
    }

    public function testAFormSendsOtherNotificationsUnderConditions(): void
    {
        $form = $this->registration();
        self::assertSame([], $form->notifications());
        $form->notifyAlso([
            Notification::to(['blue@example.com', 'blue@example.com'], 'Blue: {name}', ['==' => [['var' => 'team'], 'Blue']]),
            Notification::to(['all@example.com']),
        ]);

        [$blue, $all] = $form->notifications();
        self::assertSame([['blue@example.com'], 'Blue: {name}', ['==' => [['var' => 'team'], 'Blue']]], [$blue->recipients, $blue->subject, $blue->when]);
        self::assertSame([['all@example.com'], null, null], [$all->recipients, $all->subject, $all->when]);
        self::assertSame(['name'], $blue->placeholders());
        self::assertSame(20, Form::MAX_NOTIFICATIONS);
        $form->notifyAlso(array_fill(0, 20, Notification::to(['all@example.com'])));
        self::assertCount(20, $form->notifications());

        foreach ([
            [static fn () => $form->notifyAlso(array_fill(0, 21, Notification::to(['all@example.com']))), 'A form sends at most 20 other notifications.'],
            [static fn () => $form->notifyAlso([Notification::to(['a@example.com'], when: ['==' => [['var' => 'nope'], 'x']])]), 'A condition reads "nope", which is not a field asked before it.'],
            [static fn () => $form->notifyAlso([Notification::to(['a@example.com'], '{health}')]), 'The subject of a notification names "health", which is not a text field of the form that may be shown.'],
            [static fn () => $form->notifyAlso([Notification::to(['a@example.com'], '{captain}')]), 'The subject of a notification names "captain", which is not a text field of the form that may be shown.'],
            [static fn () => $form->notifyAlso([Notification::to(['a@example.com'], '{nope}')]), 'The subject of a notification names "nope", which is not a text field of the form that may be shown.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidForm $invalidForm) {
                self::assertSame($message, $invalidForm->getMessage());
            }
        }

        $form->notifyAlso([Notification::to(['blue@example.com'], 'Blue: {name}', ['==' => [['var' => 'team'], 'Blue']])]);
        $this->expectExceptionMessageIsOrContains('A condition reads "team", which is not a field asked before it.');
        $form->define([Field::of('name', 'Name', FieldType::Text)]);
    }

    public function testANotificationsSubjectNamesValuesOnOneLine(): void
    {
        $notification = Notification::to(['a@example.com'], ' {name} of {team}, {name} {missing} ');

        self::assertSame(['name', 'team', 'missing'], $notification->placeholders());
        self::assertSame('Ada Lovelace of , Ada Lovelace', $notification->subjectFor('Title', ['name' => "Ada\r\n  Lovelace", 'team' => true]));
        self::assertSame(255, mb_strlen($notification->subjectFor('Title', ['name' => str_repeat('é', 300)])));
        self::assertSame('Title', Notification::to(['a@example.com'])->subjectFor('Title', ['name' => 'Ada']));
        self::assertSame([], Notification::to(['a@example.com'], 'No {Name} {1a}')->placeholders());
        self::assertSame(str_repeat('é', 255), Notification::to(['a@example.com'], str_repeat('é', 255))->subject);
        self::assertSame('Subject', Notification::to(['a@example.com'], ' Subject ')->subject);
        self::assertEquals($notification, Notification::fromArray($notification->toArray()));
        self::assertSame(['recipients' => ['a@example.com'], 'subject' => '{name} of {team}, {name} {missing}', 'when' => null], $notification->toArray());
        self::assertCount(50, Notification::to(array_map(static fn (int $i): string => 'a'.$i.'@example.com', range(1, 50)))->recipients);
        self::assertSame(50, Notification::MAX_RECIPIENTS);

        foreach ([
            [static fn (): Notification => Notification::to([]), 'A notification goes to 1 to 50 addresses.'],
            [static fn (): Notification => Notification::to(array_map(static fn (int $i): string => 'a'.$i.'@example.com', range(1, 51))), 'A notification goes to 1 to 50 addresses.'],
            [static fn (): Notification => Notification::to(['nobody']), '"nobody" is not an email address.'],
            [static fn (): Notification => Notification::to(['a@example.com'], ' '), 'The subject of a notification is one line of 1 to 255 characters.'],
            [static fn (): Notification => Notification::to(['a@example.com'], str_repeat('é', 256)), 'The subject of a notification is one line of 1 to 255 characters.'],
            [static fn (): Notification => Notification::to(['a@example.com'], "A\nBcc: x@example.com"), 'The subject of a notification is one line of 1 to 255 characters.'],
            [static fn (): Notification => Notification::to(['a@example.com'], "A\rB"), 'The subject of a notification is one line of 1 to 255 characters.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidForm $invalidForm) {
                self::assertSame($message, $invalidForm->getMessage());
            }
        }
    }

    public function testSensitiveValuesAreSealedForTheEntryAndTheField(): void
    {
        $form = $this->registration();
        $sealer = new class implements FieldSealer {
            #[Override]
            public function seal(#[SensitiveParameter] string $value, string $context): string
            {
                return 'sealed('.$value.'|'.$context.')';
            }

            #[Override]
            public function open(string $sealed, string $context): string
            {
                return $sealed;
            }
        };
        $at = new DateTimeImmutable('2026-11-11 12:00 UTC');

        $entry = $form->submit(['name' => 'Ada', 'health' => ' Asthma '], $at, sealer: $sealer);
        self::assertSame(['name' => 'Ada', 'team' => null, 'health' => 'sealed(Asthma|phil_forms.entry.'.$entry->id.'.health)'], $entry->values);
        self::assertSame(['health'], $entry->sealed);
        self::assertSame('phil_forms.entry.e.f', Entry::context('e', 'f'));

        $empty = $form->submit(['name' => 'Ada'], $at, sealer: $sealer);
        self::assertSame([[], null], [$empty->sealed, $empty->values['health']]);
        $clear = $form->submit(['name' => 'Ada', 'health' => 'Asthma'], $at, allowUnsealed: true);
        self::assertSame([[], 'Asthma'], [$clear->sealed, $clear->values['health']]);
        self::assertSame(['name' => 'Ada', 'team' => null, 'meal' => 'Other', 'health' => null], $form->submit(['name' => 'Ada', 'meal' => 'Other'], $at, tiers: ['vip'], sealer: $sealer)->values);
    }

    public function testABirthDateIsBoundedByTheDayTheEntryComes(): void
    {
        $form = Form::create('a', 'A', [Field::of('born', 'Born', FieldType::BirthDate)]);

        self::assertSame(['born' => '2026-11-11'], $form->submit(['born' => '2026-11-11'], new DateTimeImmutable('2026-11-11 12:00 UTC'))->values);
        $this->expectExceptionMessageIsOrContains('The entry is refused: born: birth_date.');
        $form->submit(['born' => '2026-11-12'], new DateTimeImmutable('2026-11-11 12:00 UTC'));
    }
}
