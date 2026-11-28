<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Infrastructure;

use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(ShiftType::class)]
final class ShiftTypeTest extends TestCase
{
    public function testANewShiftIsReadInTheTimeZoneOfThePages(): void
    {
        $form = $this->factory()->create(ShiftType::class, ShiftType::data());
        $form->submit($this->fields());

        self::assertTrue($form->isSynchronized());
        $shift = ShiftType::shift($form);
        self::assertNotNull($shift);
        self::assertSame('desk-am', $shift->id);
        self::assertSame('2026-11-28 14:00:00', $shift->startsAt->format('Y-m-d H:i:s'));
        self::assertSame(180, $shift->minutes);
        self::assertSame(2, $shift->capacity);
        self::assertSame(1, $shift->reserve);
        self::assertSame(AssignmentMode::SelfSignUp, $shift->mode);
        self::assertNull($shift->requiredRole);
        self::assertTrue($shift->critical);
        self::assertSame(150, $shift->weight);
        self::assertFalse($shift->swapApproval);
        self::assertSame('Hall A', $shift->location);
        self::assertSame('Greet people', $shift->description);
        self::assertSame([], iterator_to_array($form->getErrors()));
    }

    public function testATaskWithItsMinutesAndARole(): void
    {
        $form = $this->factory()->create(ShiftType::class, ShiftType::data());
        $form->submit(['kind' => 'task', 'minutes' => '45', 'mode' => 'interest', 'required_role' => 'FIRST_AID', 'swap_approval' => '1'] + $this->fields());

        $shift = ShiftType::shift($form);

        self::assertNotNull($shift);
        self::assertSame(ShiftKind::Task, $shift->kind);
        self::assertSame(45, $shift->minutes);
        self::assertSame(AssignmentMode::Interest, $shift->mode);
        self::assertSame('FIRST_AID', $shift->requiredRole);
        self::assertTrue($shift->swapApproval);
    }

    public function testTheRefusalOfTheLibraryIsAnErrorOfTheForm(): void
    {
        $form = $this->factory()->create(ShiftType::class, ShiftType::data());
        $form->submit(['ends_at' => '2026-11-28T08:00'] + $this->fields());

        self::assertNull(ShiftType::shift($form));
        self::assertSame('The window of a shift ends 5 minutes to 31 days after it starts.', $this->errors($form)[0]);

        $incomplete = $this->factory()->create(ShiftType::class, ShiftType::data());
        $incomplete->submit(['starts_at' => ''] + $this->fields());
        self::assertNull(ShiftType::shift($incomplete));
        self::assertSame(['form.incomplete'], $this->errors($incomplete));
    }

    public function testAnExistingShiftKeepsItsIdentifier(): void
    {
        $shift = Stage::desk(role: 'FIRST_AID', approval: true);
        $data = ShiftType::data($shift);
        $form = $this->factory()->create(ShiftType::class, $data, ['existing' => true]);

        self::assertTrue($form->get('id')->isDisabled());
        self::assertFalse($this->factory()->create(ShiftType::class, ShiftType::data())->get('id')->isDisabled());
        self::assertNull($data['minutes']);
        self::assertSame('FIRST_AID', $data['required_role']);
        self::assertTrue($data['swap_approval']);
        self::assertSame(120, ShiftType::data(Stage::cloakroom())['minutes']);
        self::assertSame(['kind' => ShiftKind::Fixed, 'mode' => AssignmentMode::SelfSignUp, 'capacity' => 1, 'reserve' => 0, 'weight' => 100, 'critical' => false, 'swap_approval' => false], ShiftType::data());

        $form->submit(['id' => 'hijack', 'capacity' => '5'] + $this->fields());
        $corrected = ShiftType::shift($form, 'desk-am');
        self::assertNotNull($corrected);
        self::assertSame('desk-am', $corrected->id);
        self::assertSame(5, $corrected->capacity);
        self::assertSame('phil_shifts', $form->getConfig()->getOption('translation_domain'));
        self::assertSame('phil_shifts_shift', $form->getConfig()->getOption('csrf_token_id'));
    }

    public function testEveryFieldKeepsItsOptions(): void
    {
        $form = $this->factory()->create(ShiftType::class, ShiftType::data());
        $window = ['input' => 'datetime_immutable', 'widget' => 'single_text', 'model_timezone' => 'UTC', 'view_timezone' => 'America/Toronto'];
        $expected = [
            'id' => [TextType::class, 'form.id', true, null, ['maxlength' => 64, 'pattern' => '[a-z0-9][a-z0-9_.\-]{0,63}']],
            'title' => [TextType::class, 'form.title', true, null, ['maxlength' => 200]],
            'kind' => [EnumType::class, 'form.kind', true, null, []],
            'starts_at' => [DateTimeType::class, 'form.starts_at', true, null, []],
            'ends_at' => [DateTimeType::class, 'form.ends_at', true, null, []],
            'minutes' => [IntegerType::class, 'form.minutes', false, 'form.minutes_help', ['min' => 5, 'max' => 1440]],
            'capacity' => [IntegerType::class, 'form.capacity', true, null, ['min' => 1, 'max' => Shift::MAX_CAPACITY]],
            'reserve' => [IntegerType::class, 'form.reserve', true, null, ['min' => 0, 'max' => Shift::MAX_RESERVE]],
            'mode' => [EnumType::class, 'form.mode', true, null, []],
            'required_role' => [TextType::class, 'form.required_role', false, null, ['maxlength' => 64]],
            'critical' => [CheckboxType::class, 'form.critical', false, null, []],
            'weight' => [IntegerType::class, 'form.weight', true, 'form.weight_help', ['min' => Shift::MIN_WEIGHT, 'max' => Shift::MAX_WEIGHT]],
            'swap_approval' => [CheckboxType::class, 'form.swap_approval', false, null, []],
            'location' => [TextType::class, 'form.location', false, null, ['maxlength' => 200]],
            'description' => [TextareaType::class, 'form.description', false, null, ['maxlength' => 2000]],
        ];

        self::assertSame(array_keys($expected), array_keys($form->all()));

        foreach ($expected as $name => [$type, $label, $required, $help, $attr]) {
            $config = $form->get($name)->getConfig();
            self::assertSame($type, $config->getType()->getInnerType()::class, $name);
            self::assertSame($label, $config->getOption('label'), $name);
            self::assertSame($required, $config->getOption('required'), $name);
            self::assertSame($help, $config->getOption('help'), $name);
            self::assertSame($attr, $config->getOption('attr'), $name);
            self::assertFalse($config->getOption('disabled'), $name);
        }

        foreach (['starts_at', 'ends_at'] as $name) {
            $config = $form->get($name)->getConfig();

            foreach ($window as $option => $value) {
                self::assertSame($value, $config->getOption($option), $name.' '.$option);
            }
        }

        $kind = $form->get('kind')->getConfig()->getOption('choice_label');
        $mode = $form->get('mode')->getConfig()->getOption('choice_label');
        self::assertIsCallable($kind);
        self::assertIsCallable($mode);
        self::assertSame('kind.task', $kind(ShiftKind::Task));
        self::assertSame('mode.interest', $mode(AssignmentMode::Interest));
        self::assertSame(ShiftKind::class, $form->get('kind')->getConfig()->getOption('class'));
        self::assertSame(AssignmentMode::class, $form->get('mode')->getConfig()->getOption('class'));
        self::assertTrue($this->factory()->create(ShiftType::class, ShiftType::data(), ['existing' => true])->get('id')->getConfig()->getOption('disabled'));

        $this->expectException(InvalidOptionsException::class);
        $this->factory()->create(ShiftType::class, ShiftType::data(), ['existing' => 'yes']);
    }

    public function testTheDataOfAShiftIsEveryFieldOfTheForm(): void
    {
        $shift = new Shift('c', 'Cloakroom', ShiftKind::Duration, Stage::at('08:00:00'), Stage::at('20:00:00'), 120, 5, AssignmentMode::Interest, 3, 'FIRST_AID', true, 150, true, 'Hall B', 'Coats');

        self::assertEquals([
            'id' => 'c',
            'title' => 'Cloakroom',
            'kind' => ShiftKind::Duration,
            'starts_at' => Stage::at('08:00:00'),
            'ends_at' => Stage::at('20:00:00'),
            'minutes' => 120,
            'capacity' => 5,
            'reserve' => 3,
            'mode' => AssignmentMode::Interest,
            'required_role' => 'FIRST_AID',
            'critical' => true,
            'weight' => 150,
            'swap_approval' => true,
            'location' => 'Hall B',
            'description' => 'Coats',
        ], ShiftType::data($shift));
        self::assertSame(['id', 'title', 'kind', 'starts_at', 'ends_at', 'minutes', 'capacity', 'reserve', 'mode', 'required_role', 'critical', 'weight', 'swap_approval', 'location', 'description'], array_keys(ShiftType::data($shift)));
    }

    public function testAFormMissingAnyOfItsFourKeyFieldsIsIncomplete(): void
    {
        $complete = ['starts_at' => Stage::at('09:00:00'), 'ends_at' => Stage::at('10:00:00'), 'kind' => ShiftKind::Fixed, 'mode' => AssignmentMode::SelfSignUp];

        foreach (array_keys($complete) as $missing) {
            $data = $complete;
            unset($data[$missing]);
            $form = $this->stubbedForm($data);
            $form->expects(self::once())->method('addError')->with(self::callback(static fn (FormError $error): bool => 'form.incomplete' === $error->getMessage()));

            self::assertNull(ShiftType::shift($form), $missing);
        }
    }

    public function testWhatTheFormDoesNotSayTakesItsDefault(): void
    {
        $form = self::createStub(FormInterface::class);
        $form->method('getData')->willReturn(['id' => 'ignored', 'title' => 'Desk', 'starts_at' => Stage::at('09:00:00'), 'ends_at' => Stage::at('10:00:00'), 'kind' => ShiftKind::Fixed, 'mode' => AssignmentMode::SelfSignUp]);

        $shift = ShiftType::shift($form, 'given');

        self::assertNotNull($shift);
        self::assertSame('given', $shift->id);
        self::assertSame(1, $shift->capacity);
        self::assertSame(0, $shift->reserve);
        self::assertSame(100, $shift->weight);
        self::assertFalse($shift->critical);
        self::assertFalse($shift->swapApproval);
        self::assertNull($shift->requiredRole);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>&MockObject
     */
    private function stubbedForm(array $data): FormInterface
    {
        $form = self::createMock(FormInterface::class);
        $form->method('getData')->willReturn($data);

        return $form;
    }

    /**
     * @return array<string, string>
     */
    private function fields(): array
    {
        return [
            'id' => 'desk-am',
            'title' => 'Info desk',
            'kind' => 'fixed',
            'starts_at' => '2026-11-28T09:00',
            'ends_at' => '2026-11-28T12:00',
            'minutes' => '',
            'capacity' => '2',
            'reserve' => '1',
            'mode' => 'self_sign_up',
            'required_role' => '',
            'critical' => '1',
            'weight' => '150',
            'location' => 'Hall A',
            'description' => 'Greet people',
        ];
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     *
     * @return list<string>
     */
    private function errors(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages;
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new ShiftType('America/Toronto'))->getFormFactory();
    }
}
