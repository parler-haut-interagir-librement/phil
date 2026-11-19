<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * The desk's loan: the barcode scanned and the member's identifier — a card number, an account number —, never an
 * e-mail address.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class LendType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barcode', TextType::class, ['label' => 'desk.barcode', 'constraints' => [new NotBlank(), new Length(max: 64)], 'attr' => ['autocomplete' => 'off', 'autofocus' => true]])
            ->add('member', TextType::class, ['label' => 'desk.member', 'constraints' => [new NotBlank(), new Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', message: 'A member is named by their card or account number, never by an e-mail address.')], 'attr' => ['autocomplete' => 'off']]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'phil_library', 'csrf_token_id' => 'phil_library_lend']);
    }
}
