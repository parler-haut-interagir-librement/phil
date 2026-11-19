<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Override;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The desk's return: the barcode scanned, and the condition the copy comes back in with a note — the pieces missing —
 * when it changed.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ReturnType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barcode', TextType::class, ['label' => 'desk.barcode', 'constraints' => [new NotBlank(), new Length(max: 64)], 'attr' => ['autocomplete' => 'off']])
            ->add('condition', EnumType::class, ['label' => 'desk.condition', 'class' => CopyCondition::class, 'required' => false, 'placeholder' => 'desk.condition_unchanged', 'choice_label' => static fn (CopyCondition $condition): string => 'condition.'.$condition->value])
            ->add('note', TextareaType::class, ['label' => 'desk.note', 'required' => false, 'constraints' => [new Length(max: 1000)]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'phil_library', 'csrf_token_id' => 'phil_library_return']);
    }
}
