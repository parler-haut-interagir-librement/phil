<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Infrastructure\Form;

use Closure;
use Locale;
use Override;
use Phil\TaxonomyBundle\Application\CategoryNode;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use WeakMap;

/**
 * A category picked from the tree, named in the request's language and indented by depth; `multiple` for several.
 * The choices are the categories themselves, in the tree's order, and their labels are built apart: two categories of
 * the same name at the same depth are two choices. The default choices are named by the tree they come from, which
 * reads the translations once; choices given are named with one read of their own.
 *
 * @extends AbstractType<Category|list<Category>|null>
 */
final class CategoryType extends AbstractType
{
    /**
     * The names of the tree each resolution of the options read its default choices from, by identifier.
     *
     * @var WeakMap<Options<array<string, mixed>>, array<string, string>>
     */
    private readonly WeakMap $treeNames;

    public function __construct(
        private readonly Taxonomy $taxonomy,
    ) {
        $this->treeNames = new WeakMap();
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locale' => null,
            'choices' => $this->treeChoices(...),
            'choice_label' => $this->labels(...),
            'choice_value' => static fn (?Category $category): ?string => $category?->id,
            'choice_translation_domain' => false,
        ]);
        $resolver->setAllowedTypes('locale', ['null', 'string']);
    }

    #[Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * The categories of the tree in the language of the options, their names kept for the labels.
     *
     * @param Options<array<string, mixed>> $options
     *
     * @return list<Category>
     */
    private function treeChoices(Options $options): array
    {
        $names = [];
        $choices = $this->choices($this->taxonomy->tree($this->locale($options)), $names);
        $this->treeNames[$options] = $names;

        return $choices;
    }

    /**
     * The categories of the tree, each parent before its children, and their names by identifier.
     *
     * @param list<CategoryNode>    $nodes
     * @param array<string, string> $names
     *
     * @return list<Category>
     */
    private function choices(array $nodes, array &$names): array
    {
        $choices = [];

        foreach ($nodes as $node) {
            $names[$node->category->id] = $node->name;
            $choices = [...$choices, $node->category, ...$this->choices($node->children, $names)];
        }

        return $choices;
    }

    /**
     * A category's label: its name in the language, after a dash per level of depth.
     *
     * @param Options<array<string, mixed>> $options
     *
     * @return Closure(mixed): string
     */
    private function labels(Options $options): Closure
    {
        // Resolving the default choices keeps the names of their tree.
        $choices = $options['choices'];
        $names = $this->treeNames[$options] ?? $this->names(\is_array($choices) ? $choices : [], $this->locale($options));

        return static fn (mixed $choice): string => $choice instanceof Category ? str_repeat('— ', $choice->depth()).($names[$choice->id] ?? $choice->key()) : '';
    }

    /**
     * The names of the categories among choices given, read at once.
     *
     * @param array<array-key, mixed> $choices
     *
     * @return array<string, string>
     */
    private function names(array $choices, string $locale): array
    {
        $categories = [];

        array_walk_recursive($choices, static function (mixed $choice) use (&$categories): void {
            if ($choice instanceof Category) {
                $categories[] = $choice;
            }
        });

        return $this->taxonomy->names($categories, $locale);
    }

    /**
     * @param Options<array<string, mixed>> $options
     */
    private function locale(Options $options): string
    {
        return \is_string($options['locale']) ? $options['locale'] : Locale::getDefault();
    }
}
