<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Infrastructure\Form;

use Override;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Tag names, typed as text separated by commas, to a list for `Taxonomy::setTags()`: at most `max_tags` of them,
 * 20 by default and never above `Taxonomy::MAX_TAGS`, each at most as long as a tag name may be.
 *
 * @extends AbstractType<list<string>>
 */
final class TagsType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        \assert(\is_int($options['max_tags']));
        $builder->addModelTransformer(new TagsTransformer($options['max_tags']));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['required' => false, 'help' => 'taxonomy.help.tags', 'translation_domain' => 'phil_taxonomy', 'max_tags' => 20]);
        $resolver->setAllowedTypes('max_tags', 'int');
        $resolver->setAllowedValues('max_tags', static fn (int $max): bool => $max >= 1 && $max <= Taxonomy::MAX_TAGS);
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }
}
