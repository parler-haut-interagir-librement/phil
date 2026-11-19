<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Form;

use Override;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An event, to an `EventDraft`: its title, description, place and page, its image, and its venue among the calendar's
 * when there is a venue repository; its start and end, on the wall clock of its time zone, or whole days; the rule it
 * repeats by, `phil/recurrence-bundle`'s field; its tags, separated by commas.
 *
 * @extends AbstractType<EventDraft>
 */
final class EventType extends AbstractType
{
    public function __construct(
        private readonly string $defaultTimezone = 'UTC',
        private readonly ?VenueRepository $venues = null,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $time = ['input' => 'datetime_immutable', 'view_timezone' => 'UTC', 'model_timezone' => 'UTC'];

        $builder
            ->add('title', TextType::class, ['label' => 'calendar.field.title', 'attr' => ['maxlength' => 200]])
            ->add('start', DateTimeType::class, ['label' => 'calendar.field.start'] + $time)
            ->add('end', DateTimeType::class, ['label' => 'calendar.field.end'] + $time)
            ->add('allDay', CheckboxType::class, ['label' => 'calendar.field.all_day', 'required' => false])
            ->add('timezone', TimezoneType::class, ['label' => 'calendar.field.timezone', 'empty_data' => $this->defaultTimezone])
            ->add('rule', RecurrenceRuleType::class, ['label' => 'calendar.field.rule', 'required' => false])
            ->add('location', TextType::class, ['label' => 'calendar.field.location', 'required' => false, 'attr' => ['maxlength' => 255]])
            ->add('url', UrlType::class, ['label' => 'calendar.field.url', 'required' => false, 'default_protocol' => 'https'])
            ->add('image', TextType::class, ['label' => 'calendar.field.image', 'required' => false, 'help' => 'calendar.help.image', 'attr' => ['maxlength' => 2048]])
            ->add('description', TextareaType::class, ['label' => 'calendar.field.description', 'required' => false])
            ->add('tags', TextType::class, ['label' => 'calendar.field.tags', 'required' => false, 'help' => 'calendar.help.tags'])
            ->add('capacity', IntegerType::class, ['label' => 'calendar.field.capacity', 'required' => false, 'help' => 'calendar.help.capacity', 'attr' => ['min' => 1]]);

        $builder->get('tags')->addModelTransformer(new TagsTransformer());

        if ($this->venues instanceof VenueRepository) {
            $venues = $this->venues;
            $builder->add('venue', ChoiceType::class, [
                'label' => 'calendar.field.venue',
                'required' => false,
                'placeholder' => 'calendar.help.no_venue',
                'choice_loader' => new CallbackChoiceLoader(static fn (): array => $venues->all()),
                'choice_value' => static fn (?Venue $venue): ?string => $venue?->id,
                'choice_label' => static fn (Venue $venue): string => $venue->label(),
                'choice_translation_domain' => false,
            ]);
        }

        // A new event starts in the default time zone, shown as such.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            if (null === $event->getData()) {
                $draft = new EventDraft();
                $draft->timezone = $this->defaultTimezone;
                $event->setData($draft);
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventDraft::class,
            'translation_domain' => 'phil_calendar',
        ]);
    }
}
