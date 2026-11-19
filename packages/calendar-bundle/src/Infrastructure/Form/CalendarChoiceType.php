<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Form;

use Override;
use Phil\CalendarBundle\Application\CalendarChoiceDraft;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A choice of a member's calendar, to a `CalendarChoiceDraft`: what it is — a host, a tag, a venue, an event —, its
 * value, and whether it is followed or hidden. `CalendarChoices::apply()` keeps it.
 *
 * @extends AbstractType<CalendarChoiceDraft>
 */
final class CalendarChoiceType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $sources = [];

        foreach (ChoiceSource::cases() as $source) {
            $sources['calendar.choice.source.'.$source->value] = $source->value;
        }

        $builder
            ->add('source', ChoiceType::class, ['label' => 'calendar.choice.source.label', 'choices' => $sources])
            ->add('value', TextType::class, ['label' => 'calendar.choice.value', 'attr' => ['maxlength' => ChoiceSource::MAX_LENGTH]])
            ->add('hidden', ChoiceType::class, [
                'label' => 'calendar.choice.mode',
                'choices' => ['calendar.choice.follow' => false, 'calendar.choice.hide' => true],
                'expanded' => true,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CalendarChoiceDraft::class, 'translation_domain' => 'phil_calendar']);
    }
}
