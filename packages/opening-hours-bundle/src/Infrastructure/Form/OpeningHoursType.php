<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Form;

use Override;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Intl\Intl;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Opening hours, to and from `OpeningHours`: a field a day, named in the request's language, where the day's ranges
 * are typed — `09:00-12:00, 13:00-17:00`, empty when closed —; the special hours, one period a line; and the time
 * zone, the bundle's `default_timezone` unless the form says otherwise. Hours with no open day and no special hours
 * are `null`.
 *
 * @extends AbstractType<OpeningHours|null>
 */
final class OpeningHoursType extends AbstractType
{
    public function __construct(
        private readonly string $defaultTimezone = 'UTC',
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?string $locale */
        $locale = $options['locale'];
        /** @var ?string $timezone */
        $timezone = $options['timezone'];

        foreach (DayOfWeek::cases() as $day) {
            $builder->add(strtolower($day->name), TextType::class, [
                'label' => mb_convert_case(Intl::day($day, $locale), \MB_CASE_TITLE),
                'translation_domain' => false,
                'required' => false,
                'invalid_message' => 'These hours cannot be read: type ranges such as 09:00-12:00, 13:00-17:00.',
                'attr' => ['placeholder' => '09:00-17:00'],
            ]);
            $builder->get(strtolower($day->name))->addModelTransformer(new DayHoursTransformer());
        }

        $builder->add('specials', TextareaType::class, [
            'label' => 'opening_hours.specials',
            'help' => 'opening_hours.specials_help',
            'required' => false,
            'invalid_message' => 'These special hours cannot be read: type one period a line, such as 2026-12-25 # Christmas.',
        ]);
        $builder->get('specials')->addModelTransformer(new SpecialHoursTransformer());

        if (true === $options['with_timezone']) {
            $builder->add('timezone', TimezoneType::class, ['label' => 'opening_hours.timezone']);
        }

        $builder->addModelTransformer(new OpeningHoursTransformer($timezone ?? $this->defaultTimezone));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'phil_opening_hours',
        ]);

        $resolver->define('timezone')->default(null)->allowedTypes('string', 'null')
            ->info("The time zone of new hours; the bundle's default_timezone when null.");
        $resolver->define('with_timezone')->default(true)->allowedTypes('bool')
            ->info('Whether the form lets people choose the time zone.');
        $resolver->define('locale')->default(null)->allowedTypes('string', 'null')
            ->info("The language the days are named in; the request's when null.");
    }
}
