<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Override;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * A new copy of a title: its barcode, its condition, and the stockroom item it is when the shelves are part of one.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class CopyType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barcode', TextType::class, ['label' => 'copy.barcode', 'constraints' => [new NotBlank(), new Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', message: 'A barcode is 1 to 64 letters, digits, dots, underscores or hyphens.')]])
            ->add('condition', EnumType::class, ['label' => 'copy.condition', 'class' => CopyCondition::class, 'data' => CopyCondition::Good, 'choice_label' => static fn (CopyCondition $condition): string => 'condition.'.$condition->value])
            ->add('stockroomItem', TextType::class, ['label' => 'copy.stockroom_item', 'required' => false, 'constraints' => [new Length(max: 128)], 'help' => 'copy.stockroom_item_help']);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'phil_library', 'csrf_token_id' => 'phil_library_copy']);
    }
}
