<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Form;

use Override;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form where a member chooses what happens to warned content: a default display, then a display per category and,
 * unless `themes` is false, per theme within its category — each left empty to follow the level above. Its data is a
 * `WarningPreferences`.
 *
 * A preference whose display is kept keeps the severity it applied from; the preferences of themes and categories the
 * form does not list are kept as they are.
 *
 * @extends AbstractType<WarningPreferences>
 */
final class WarningPreferencesType extends AbstractType
{
    public function __construct(
        private readonly CatalogueLabels $labels,
        private readonly Display $default = Display::Fold,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locale = \is_string($options['locale']) ? $options['locale'] : null;
        $catalogue = $this->labels->catalogue();
        $displays = [
            'class' => Display::class,
            'choice_label' => static fn (Display $display): string => 'display.'.$display->value,
            'translation_domain' => 'phil_content_warnings',
        ];

        $builder->add('default', EnumType::class, [...$displays, 'label' => 'preferences.default']);
        $categories = $builder->create('categories', FormType::class, ['label' => 'preferences.categories', 'translation_domain' => 'phil_content_warnings']);
        $themes = $builder->create('themes', FormType::class, ['label' => 'preferences.themes', 'translation_domain' => 'phil_content_warnings']);

        foreach ($catalogue->categories() as $category) {
            $label = $this->labels->category($category->key, $locale);
            $categories->add($category->key, EnumType::class, [...$displays, 'label' => $label, 'required' => false, 'placeholder' => 'preferences.inherit']);
            $inCategory = $themes->create($category->key, FormType::class, ['label' => $label, 'translation_domain' => false]);

            foreach ($catalogue->themesIn($category->key) as $theme) {
                $inCategory->add($theme->key, EnumType::class, [...$displays, 'label' => $this->labels->theme($theme->key, $locale), 'required' => false, 'placeholder' => 'preferences.inherit']);
            }

            $themes->add($inCategory);
        }

        $builder->add($categories);

        if (true === $options['themes']) {
            $builder->add($themes);
        }

        $builder->addModelTransformer(new WarningPreferencesTransformer($catalogue, $this->default));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'themes' => true,
            'data_class' => null,
            'locale' => null,
            'label' => false,
            'translation_domain' => 'phil_content_warnings',
        ]);
        $resolver->setAllowedTypes('themes', 'bool');
        $resolver->setAllowedTypes('locale', ['null', 'string']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_content_warnings_preferences';
    }
}
