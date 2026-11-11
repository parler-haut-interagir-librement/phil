<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Infrastructure\Form;

use Override;
use Phil\ContactFormBundle\Application\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The "write to us" form, to a `ContactMessage`, protected by `phil/spam-protection-bundle`: its subject and message
 * are read by the content rules.
 *
 * @extends AbstractType<ContactMessage>
 */
final class ContactFormType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'contact_form.name', 'attr' => ['autocomplete' => 'name', 'maxlength' => 100]])
            ->add('email', EmailType::class, ['label' => 'contact_form.email', 'attr' => ['autocomplete' => 'email', 'maxlength' => 254]])
            ->add('subject', TextType::class, ['label' => 'contact_form.subject', 'required' => false, 'attr' => ['maxlength' => 150]])
            ->add('message', TextareaType::class, ['label' => 'contact_form.message', 'attr' => ['rows' => 8, 'maxlength' => 5000]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
            'translation_domain' => 'phil_contact_form',
            'spam_protection' => ['scope' => 'contact_form', 'content_fields' => ['subject', 'message']],
        ]);
    }
}
