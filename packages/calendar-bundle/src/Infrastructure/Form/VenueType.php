<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Form;

use Override;
use Phil\CalendarBundle\Application\VenueDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A venue, to a `VenueDraft`: its name, room, address, coordinates, and the listing of the directory it is, as
 * `kind:reference`.
 *
 * @extends AbstractType<VenueDraft>
 */
final class VenueType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'calendar.venue.name', 'attr' => ['maxlength' => 200]])
            ->add('room', TextType::class, ['label' => 'calendar.venue.room', 'required' => false, 'attr' => ['maxlength' => 100]])
            ->add('address', TextType::class, ['label' => 'calendar.venue.address', 'required' => false, 'attr' => ['maxlength' => 500]])
            ->add('latitude', NumberType::class, ['label' => 'calendar.venue.latitude', 'required' => false, 'scale' => 6])
            ->add('longitude', NumberType::class, ['label' => 'calendar.venue.longitude', 'required' => false, 'scale' => 6])
            ->add('listing', TextType::class, ['label' => 'calendar.venue.listing', 'required' => false, 'help' => 'calendar.help.listing']);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => VenueDraft::class, 'translation_domain' => 'phil_calendar']);
    }
}
