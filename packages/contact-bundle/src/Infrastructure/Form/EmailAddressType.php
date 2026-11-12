<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\Email;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An email address in one field, to and from an `Email`.
 *
 * @extends AbstractType<Email|null>
 */
final class EmailAddressType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new EmailTransformer());
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'This value is not a valid email address.',
            'attr' => ['autocomplete' => 'email'],
        ]);
    }

    #[Override]
    public function getParent(): string
    {
        return EmailType::class;
    }
}
