<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A phone line as two fields, its role and its number, to and from a `PhoneLine`. The number is read in the
 * `region` of `phil/phone-bundle`'s field.
 *
 * @extends AbstractType<PhoneLine|null>
 */
final class PhoneLineType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', EnumType::class, [
                'class' => PhoneRole::class,
                'label' => false,
                'choice_label' => static fn (PhoneRole $role): string => 'phone_role.'.$role->value,
                'choice_translation_domain' => 'phil_contact',
            ])
            ->add('number', PhoneType::class, ['label' => false, 'required' => $options['required']] + (\is_string($options['region']) ? ['region' => $options['region']] : []))
            ->addModelTransformer(new PhoneLineTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->define('region')
            ->default(null)
            ->allowedTypes('string', 'null')
            ->info("The country a number typed without its country code is read in; phil/phone-bundle's default_region when null.");
    }
}
