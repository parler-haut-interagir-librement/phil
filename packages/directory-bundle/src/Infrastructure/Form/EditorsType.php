<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Form;

use Override;
use Phil\IndexingConsent\Indexing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The editors of a listing — a user to add, by their identifier, and editors to remove —, and who finds it: listed,
 * kept out of search engines, or reachable only by its address.
 *
 * @extends AbstractType<array{add?: ?string, remove?: list<string>, indexing?: Indexing}>
 */
final class EditorsType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $editors */
        $editors = $options['editors'];

        $builder
            ->add('add', TextType::class, ['label' => 'phil_directory.editor_to_add', 'required' => false])
            ->add('remove', ChoiceType::class, [
                'label' => 'phil_directory.editors_to_remove',
                'choices' => array_combine($editors, $editors),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('indexing', EnumType::class, [
                'label' => 'phil_directory.indexing',
                'class' => Indexing::class,
                'data' => $options['indexing'],
                'expanded' => true,
                'choice_label' => static fn (Indexing $indexing): string => 'phil_directory.indexing_'.$indexing->value,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['editors' => [], 'indexing' => Indexing::Listed, 'translation_domain' => 'phil_directory'])
            ->setAllowedTypes('editors', 'string[]')
            ->setAllowedTypes('indexing', Indexing::class);
    }
}
