<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Form;

use Override;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field that picks the warnings of a piece of content among the themes of the catalogue, grouped by category and
 * labelled in the current locale or the `locale` option; its data is a `ContentWarnings`, or null for none.
 *
 * Checkboxes by default; `expanded: false` gives a multiple select. A warning kept keeps its severity and its note,
 * and a warning whose theme the catalogue no longer has is kept as it is: the field only adds and removes the
 * warnings of the themes it lists.
 *
 * @extends AbstractType<ContentWarnings|null>
 */
final class ContentWarningsType extends AbstractType
{
    public function __construct(
        private readonly CatalogueLabels $labels,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new ContentWarningsTransformer($this->labels->catalogue()));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => true,
            'locale' => null,
            'choices' => fn (Options $options): array => $this->labels->choices(\is_string($options['locale']) ? $options['locale'] : null),
            'choice_translation_domain' => false,
            'label' => 'form.warnings',
            'translation_domain' => 'phil_content_warnings',
        ]);
        $resolver->setAllowedTypes('locale', ['null', 'string']);
        $resolver->setAllowedValues('multiple', true);
    }

    #[Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_content_warnings';
    }
}
