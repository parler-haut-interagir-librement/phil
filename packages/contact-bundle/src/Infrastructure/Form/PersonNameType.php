<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A person name in its parts, to and from a `PersonName`: honorific, given name, additional names separated by spaces,
 * particle, family name, second family name, generation, credential, the order it is written in when it is not its
 * culture's, and its culture, a BCP 47 language tag. With the `culture` option, every name takes that culture and the
 * form has no culture field. A name without given nor family name is `null`.
 *
 * @extends AbstractType<PersonName|null>
 */
final class PersonNameType extends AbstractType
{
    /**
     * Each field, with the `autocomplete` token browsers fill it with.
     */
    private const array FIELDS = [
        'honorific' => 'honorific-prefix',
        'given' => 'given-name',
        'additional' => 'additional-name',
        'particle' => null,
        'family' => 'family-name',
        'secondFamily' => null,
        'generation' => 'honorific-suffix',
        'credential' => null,
    ];

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::FIELDS as $field => $autocomplete) {
            $builder->add($field, TextType::class, [
                'label' => 'person_name.'.$field,
                'required' => false,
                'attr' => null === $autocomplete ? [] : ['autocomplete' => $autocomplete],
            ]);
        }

        $builder->add('order', EnumType::class, [
            'class' => NameOrder::class,
            'label' => 'person_name.order',
            'required' => false,
            'placeholder' => 'person_name.order.culture',
            'choice_label' => static fn (NameOrder $order): string => 'person_name.order.'.$order->value,
        ]);

        /** @var ?string $culture */
        $culture = $options['culture'];

        if (null === $culture) {
            $builder->add('culture', TextType::class, ['label' => 'person_name.culture', 'required' => false, 'attr' => ['autocomplete' => 'off', 'spellcheck' => 'false']]);
        }

        $builder->addModelTransformer(new PersonNameTransformer($culture));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'phil_contact',
            'invalid_message' => 'This name is not valid.',
        ]);

        $resolver->define('culture')->default(null)->allowedTypes('string', 'null')
            ->info('The BCP 47 language tag every name entered takes, which orders and sorts it: "fr", "nl-BE"; a field of the form when null.');
    }
}
