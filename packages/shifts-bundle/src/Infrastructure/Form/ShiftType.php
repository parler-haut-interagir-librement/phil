<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Form;

use DateTimeImmutable;
use Override;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A shift in a coordinator's form: its identifier — fixed once the shift exists —, title, kind, window in the
 * configured time zone, minutes, places, reserve, mode, required role, critical flag, weight, swap approval, location
 * and description. The form holds an array; `shift()` builds the shift, or adds the library's refusal to the form.
 *
 * ```php
 * $form = $factory->create(ShiftType::class, ShiftType::data($roster->shift), ['existing' => true]);
 * ```
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ShiftType extends AbstractType
{
    public function __construct(
        private readonly string $timeZone,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $window = ['input' => 'datetime_immutable', 'widget' => 'single_text', 'model_timezone' => 'UTC', 'view_timezone' => $this->timeZone];

        $builder
            ->add('id', TextType::class, ['label' => 'form.id', 'disabled' => true === $options['existing'], 'attr' => ['maxlength' => 64, 'pattern' => '[a-z0-9][a-z0-9_.\-]{0,63}']])
            ->add('title', TextType::class, ['label' => 'form.title', 'attr' => ['maxlength' => 200]])
            ->add('kind', EnumType::class, ['label' => 'form.kind', 'class' => ShiftKind::class, 'choice_label' => static fn (ShiftKind $kind): string => 'kind.'.$kind->value])
            ->add('starts_at', DateTimeType::class, ['label' => 'form.starts_at', ...$window])
            ->add('ends_at', DateTimeType::class, ['label' => 'form.ends_at', ...$window])
            ->add('minutes', IntegerType::class, ['label' => 'form.minutes', 'required' => false, 'help' => 'form.minutes_help', 'attr' => ['min' => Shift::MIN_MINUTES, 'max' => Shift::MAX_MINUTES]])
            ->add('capacity', IntegerType::class, ['label' => 'form.capacity', 'attr' => ['min' => 1, 'max' => Shift::MAX_CAPACITY]])
            ->add('reserve', IntegerType::class, ['label' => 'form.reserve', 'attr' => ['min' => 0, 'max' => Shift::MAX_RESERVE]])
            ->add('mode', EnumType::class, ['label' => 'form.mode', 'class' => AssignmentMode::class, 'choice_label' => static fn (AssignmentMode $mode): string => 'mode.'.$mode->value])
            ->add('required_role', TextType::class, ['label' => 'form.required_role', 'required' => false, 'attr' => ['maxlength' => 64]])
            ->add('critical', CheckboxType::class, ['label' => 'form.critical', 'required' => false])
            ->add('weight', IntegerType::class, ['label' => 'form.weight', 'help' => 'form.weight_help', 'attr' => ['min' => Shift::MIN_WEIGHT, 'max' => Shift::MAX_WEIGHT]])
            ->add('swap_approval', CheckboxType::class, ['label' => 'form.swap_approval', 'required' => false])
            ->add('location', TextType::class, ['label' => 'form.location', 'required' => false, 'attr' => ['maxlength' => 200]])
            ->add('description', TextareaType::class, ['label' => 'form.description', 'required' => false, 'attr' => ['maxlength' => 2000]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'existing' => false,
            'translation_domain' => 'phil_shifts',
            'csrf_token_id' => 'phil_shifts_shift',
        ]);
        $resolver->setAllowedTypes('existing', 'bool');
    }

    /**
     * The fields of a shift, to fill the form with.
     *
     * @return array<string, mixed>
     */
    public static function data(?Shift $shift = null): array
    {
        if (!$shift instanceof Shift) {
            return ['kind' => ShiftKind::Fixed, 'mode' => AssignmentMode::SelfSignUp, 'capacity' => 1, 'reserve' => 0, 'weight' => 100, 'critical' => false, 'swap_approval' => false];
        }

        return [
            'id' => $shift->id,
            'title' => $shift->title,
            'kind' => $shift->kind,
            'starts_at' => $shift->startsAt,
            'ends_at' => $shift->endsAt,
            'minutes' => ShiftKind::Fixed === $shift->kind ? null : $shift->minutes,
            'capacity' => $shift->capacity,
            'reserve' => $shift->reserve,
            'mode' => $shift->mode,
            'required_role' => $shift->requiredRole,
            'critical' => $shift->critical,
            'weight' => $shift->weight,
            'swap_approval' => $shift->swapApproval,
            'location' => $shift->location,
            'description' => $shift->description,
        ];
    }

    /**
     * The shift a submitted form describes, or null with the refusal added to the form.
     *
     * @param FormInterface<array<string, mixed>> $form
     */
    public static function shift(FormInterface $form, ?string $id = null): ?Shift
    {
        $data = $form->getData();
        $text = static fn (string $key): string => \is_string($data[$key] ?? null) ? $data[$key] : '';
        $int = static fn (string $key, int $default): int => \is_int($data[$key] ?? null) ? $data[$key] : $default;
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $kind = $data['kind'] ?? null;
        $mode = $data['mode'] ?? null;

        if (!$startsAt instanceof DateTimeImmutable || !$endsAt instanceof DateTimeImmutable || !$kind instanceof ShiftKind || !$mode instanceof AssignmentMode) {
            $form->addError(new FormError('form.incomplete'));

            return null;
        }

        try {
            return new Shift(
                $id ?? $text('id'),
                $text('title'),
                $kind,
                $startsAt,
                $endsAt,
                \is_int($data['minutes'] ?? null) ? $data['minutes'] : null,
                $int('capacity', 1),
                $mode,
                $int('reserve', 0),
                '' === $text('required_role') ? null : $text('required_role'),
                true === ($data['critical'] ?? false),
                $int('weight', 100),
                true === ($data['swap_approval'] ?? false),
                $text('location'),
                $text('description'),
            );
        } catch (InvalidShift $invalidShift) {
            $form->addError(new FormError($invalidShift->getMessage()));

            return null;
        }
    }
}
