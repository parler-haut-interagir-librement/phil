<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Infrastructure\Form;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use Override;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\Weekday;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A recurrence rule, to and from a `RecurrenceRule`, for the rules people set: every so many days, weeks, months or
 * years; on some weekdays; on a day of the month or the nth weekday; in some months; until a date or so many times.
 * Each row says for which frequencies or ends it shows, for the Stimulus controller `phil--recurrence-bundle--
 * recurrence` to show only those. No frequency is no rule.
 *
 * @extends AbstractType<RecurrenceRule|null>
 */
final class RecurrenceRuleType extends AbstractType
{
    private const string CONTROLLER = 'phil--recurrence-bundle--recurrence';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $row = static fn (string $show): array => ['row_attr' => ['data-'.self::CONTROLLER.'-target' => 'row', 'data-show' => $show]];

        $builder
            ->add('frequency', EnumType::class, [
                'class' => Frequency::class,
                'label' => 'recurrence.field.frequency',
                'placeholder' => '',
                'choice_label' => static fn (Frequency $frequency): string => 'recurrence.choice.'.strtolower($frequency->value),
                'attr' => ['data-'.self::CONTROLLER.'-target' => 'frequency', 'data-action' => self::CONTROLLER.'#show'],
            ])
            ->add('interval', IntegerType::class, ['label' => 'recurrence.field.interval', 'attr' => ['min' => 1, 'max' => RecurrenceRule::MAX_NUMBER]] + $row('DAILY WEEKLY MONTHLY YEARLY'))
            ->add('weekdays', EnumType::class, ['class' => Weekday::class, 'label' => 'recurrence.field.weekdays', 'multiple' => true, 'expanded' => true, 'choice_label' => self::weekday(...), 'choice_translation_domain' => false] + $row('DAILY WEEKLY'))
            ->add('month_day', IntegerType::class, ['label' => 'recurrence.field.month_day', 'attr' => ['min' => -31, 'max' => 31]] + $row('MONTHLY YEARLY'))
            ->add('month_weekday', ChoiceType::class, ['label' => 'recurrence.field.month_weekday', 'choices' => array_combine(array_map(static fn (int $position): string => 'recurrence.position.'.$position, [1, 2, 3, 4, 5, -1]), [1, 2, 3, 4, 5, -1]), 'placeholder' => ''] + $row('MONTHLY YEARLY'))
            ->add('weekday', EnumType::class, ['class' => Weekday::class, 'label' => 'recurrence.field.weekday', 'choice_label' => self::weekday(...), 'choice_translation_domain' => false, 'placeholder' => ''] + $row('MONTHLY YEARLY'))
            ->add('months', ChoiceType::class, ['label' => 'recurrence.field.months', 'choices' => array_combine(array_map(self::month(...), range(1, 12)), range(1, 12)), 'multiple' => true, 'choice_translation_domain' => false] + $row('YEARLY'))
            ->add('end', ChoiceType::class, [
                'label' => 'recurrence.field.end',
                'choices' => ['recurrence.choice.never' => 'never', 'recurrence.choice.count' => 'count', 'recurrence.choice.until' => 'until'],
                'attr' => ['data-'.self::CONTROLLER.'-target' => 'end', 'data-action' => self::CONTROLLER.'#show'],
            ] + $row('DAILY WEEKLY MONTHLY YEARLY'))
            ->add('count', IntegerType::class, ['label' => 'recurrence.field.count', 'attr' => ['min' => 1, 'max' => RecurrenceRule::MAX_NUMBER]] + $row('count'))
            ->add('until', DateType::class, ['label' => 'recurrence.field.until', 'widget' => 'single_text', 'input' => 'datetime_immutable'] + $row('until'));

        foreach (['interval', 'weekdays', 'month_day', 'month_weekday', 'weekday', 'months', 'count', 'until'] as $field) {
            $builder->get($field)->setRequired(false);
        }

        $builder->addModelTransformer(new RecurrenceRuleTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'phil_recurrence',
            'invalid_message' => 'This recurrence is not valid.',
            'attr' => ['data-controller' => self::CONTROLLER],
        ]);
    }

    /**
     * A weekday named in the request's language.
     */
    public static function weekday(Weekday $day): string
    {
        return self::format('EEEE', \sprintf('2024-01-%02d', $day->iso()));
    }

    public static function month(int $month): string
    {
        return self::format('MMMM', \sprintf('2024-%02d-01', $month));
    }

    private static function format(string $pattern, string $date): string
    {
        $text = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, $pattern)->format(new DateTimeImmutable($date, new DateTimeZone('UTC')));

        return false === $text ? $date : $text;
    }
}
