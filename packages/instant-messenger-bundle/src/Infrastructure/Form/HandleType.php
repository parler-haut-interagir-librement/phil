<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Infrastructure\Form;

use Override;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\MessengerType;
use Phil\InstantMessengerBundle\Application\AcceptedMessengers;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A handle as two fields, the messaging service chosen among the `types` the field accepts — every international one
 * by default — and the handle as the person types it, to and from a `Handle`. An empty handle is none.
 *
 * @extends AbstractType<Handle|null>
 */
final class HandleType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => false,
                'choices' => AcceptedMessengers::cases($options['types']),
                'choice_label' => self::label(...),
                'choice_value' => self::value(...),
                'choice_translation_domain' => false,
            ])
            ->add('handle', TextType::class, [
                'label' => false,
                'required' => $options['required'],
            ])
            ->addModelTransformer(new HandleTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'compound' => true,
            'error_bubbling' => false,
            'invalid_message' => 'This value is not a valid handle on this service.',
        ]);

        $resolver
            ->define('types')
            ->default(null)
            ->info('The services offered: an EnumSet, an enum class, cases, or a list of these; every international one by default.');
    }

    public static function label(MessengerType $type): string
    {
        return $type instanceof KnownMessenger ? $type->label() : $type->name;
    }

    /**
     * A value unique among the cases of every enum: `Phil.InstantMessenger.KnownMessenger.Telegram`.
     */
    public static function value(?MessengerType $type): string
    {
        return $type instanceof MessengerType ? str_replace('\\', '.', $type::class).'.'.$type->name : '';
    }
}
