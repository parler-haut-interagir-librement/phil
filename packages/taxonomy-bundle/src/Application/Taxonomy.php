<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Application;

use Phil\Resource\ResourceReference;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Phil\Slug\TransliteratingSlugger;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;

/**
 * Tags and a translated tree of categories, given to any resource: find or create a tag, rename and merge tags;
 * create, translate, move and merge categories; tag and categorize resources, and find them again.
 *
 * Tag names and category keys are made slugs by one slugger: by default the transliterating one of `phil/slug`, at
 * most 128 characters; any other — a named slugger of `phil/slug-bundle`, a decorated one — when given.
 */
final readonly class Taxonomy
{
    /**
     * The most tags `setTags()` gives one resource.
     */
    public const int MAX_TAGS = 100;

    public function __construct(
        private TaxonomyRepository $repository,
        private string $fallbackLocale = 'en',
        private Slugger $slugger = new TransliteratingSlugger(null, 128),
    ) {
    }

    public function tag(string $name): Tag
    {
        $slug = $this->slugOf($name);
        $existing = $this->repository->tagBySlug($slug->value);

        if ($existing instanceof Tag) {
            return $existing;
        }

        $tag = Tag::named($name, $slug);
        $this->repository->save($tag);

        return $tag;
    }

    /**
     * @throws InvalidTaxonomy when another tag has that name
     */
    public function renameTag(Tag $tag, string $name): void
    {
        $slug = $this->slugOf($name);
        $other = $this->repository->tagBySlug($slug->value);

        if ($other instanceof Tag && $other->id !== $tag->id) {
            throw InvalidTaxonomy::because(\sprintf('The tag "%s" exists: merge the two instead.', $other->name()));
        }

        $tag->rename($name, $slug);
        $this->repository->save($tag);
    }

    /**
     * @return list<Tag> by name
     */
    public function tagsOf(ResourceReference $resource): array
    {
        $tags = $this->repository->tags(array_map(static fn (Assignment $assignment): string => $assignment->termId, $this->repository->assignments($resource, Assignment::TAG)));
        usort($tags, static fn (Tag $a, Tag $b): int => $a->slug() <=> $b->slug());

        return $tags;
    }

    /**
     * Gives a resource exactly these tags, created when new.
     *
     * @param list<string> $names
     *
     * @return list<Tag> by name
     *
     * @throws InvalidTaxonomy when more than `MAX_TAGS` names are given, before any query
     */
    public function setTags(ResourceReference $resource, array $names): array
    {
        if (\count($names) > self::MAX_TAGS) {
            throw InvalidTaxonomy::because(\sprintf('A resource takes at most %d tags.', self::MAX_TAGS));
        }

        $wanted = [];

        foreach ($names as $name) {
            $tag = $this->tag($name);
            $wanted[$tag->id] = $tag;
        }

        foreach ($this->repository->assignments($resource, Assignment::TAG) as $assignment) {
            if (isset($wanted[$assignment->termId])) {
                unset($wanted[$assignment->termId]);
            } else {
                $this->repository->remove($assignment);
            }
        }

        $this->repository->save(...array_map(static fn (Tag $tag): Assignment => Assignment::ofTag($tag, $resource), $wanted));

        return $this->tagsOf($resource);
    }

    /**
     * @return list<ResourceReference>
     */
    public function taggedWith(Tag $tag, ?string $resourceKind = null): array
    {
        return $this->repository->resourcesWith(Assignment::TAG, [$tag->id], $resourceKind);
    }

    /**
     * Gives the second tag every resource the first one has, then removes the first.
     */
    public function mergeTags(Tag $from, Tag $into): void
    {
        if ($from->id === $into->id) {
            throw InvalidTaxonomy::because('A tag is not merged into itself.');
        }

        $this->repository->reassign(Assignment::TAG, $from->id, $into->id);
        $this->repository->remove($from);
    }

    /**
     * @param array<string, string> $names by locale
     *
     * @throws InvalidTaxonomy when another category has that key
     */
    public function createCategory(string $key, ?Category $parent = null, array $names = [], int $position = 0): Category
    {
        $slug = $this->slugOf($key);

        if ($this->repository->categoryByKey($slug->value) instanceof Category) {
            throw InvalidTaxonomy::because(\sprintf('A category has the key "%s".', $slug));
        }

        $category = Category::create($slug, $parent, $position);
        $this->repository->save($category);

        foreach ($names as $locale => $name) {
            $this->translate($category, $locale, $name);
        }

        return $category;
    }

    public function translate(Category $category, string $locale, string $name, ?string $description = null): void
    {
        foreach ($this->repository->translations($category) as $translation) {
            if ($translation->locale === $locale) {
                $translation->change($name, $description);
                $this->repository->save($translation);

                return;
            }
        }

        $this->repository->save(CategoryTranslation::of($category, $locale, $name, $description));
    }

    /**
     * The category's name in a language: `fr_CA`, else `fr`, else the fallback language, else its key.
     */
    public function name(Category $category, string $locale): string
    {
        return $this->names([$category], $locale)[$category->id] ?? $category->key();
    }

    /**
     * The categories' names in a language, by identifier, their translations read at once: those of the language, of
     * its base language and of the fallback language only.
     *
     * @param list<Category> $categories
     *
     * @return array<string, string>
     */
    public function names(array $categories, string $locale): array
    {
        $translated = [];
        $chain = [$locale, explode('_', $locale)[0], $this->fallbackLocale];

        foreach ($this->repository->translationsOf($categories, $chain) as $translation) {
            $translated[$translation->category->id][$translation->locale] = $translation->name();
        }

        $names = [];

        foreach ($categories as $category) {
            $own = $translated[$category->id] ?? [];
            $names[$category->id] = $own[$locale] ?? $own[explode('_', $locale)[0]] ?? $own[$this->fallbackLocale] ?? $category->key();
        }

        return $names;
    }

    /**
     * Moves a category, and its branch, under another parent or to the root.
     *
     * @throws InvalidTaxonomy when the parent is in the branch, or the branch would go deeper than `Category::MAX_DEPTH`
     */
    public function move(Category $category, ?Category $parent, int $position = 0): void
    {
        $this->repository->save(...$this->moved($category, $parent, $position, $this->repository->categories()));
    }

    /**
     * The categories as a tree, named in a language, siblings by position then key.
     *
     * @return list<CategoryNode>
     */
    public function tree(string $locale): array
    {
        $children = [];

        $categories = $this->repository->categories();

        foreach ($categories as $category) {
            $children[$category->parent()->id ?? ''][] = $category;
        }

        return $this->nodes($children, '', $this->names($categories, $locale));
    }

    /**
     * Gives a resource exactly these categories.
     *
     * @param list<Category> $categories
     */
    public function categorize(ResourceReference $resource, array $categories): void
    {
        $wanted = [];

        foreach ($categories as $category) {
            $wanted[$category->id] = $category;
        }

        foreach ($this->repository->assignments($resource, Assignment::CATEGORY) as $assignment) {
            if (isset($wanted[$assignment->termId])) {
                unset($wanted[$assignment->termId]);
            } else {
                $this->repository->remove($assignment);
            }
        }

        $this->repository->save(...array_map(static fn (Category $category): Assignment => Assignment::ofCategory($category, $resource), $wanted));
    }

    /**
     * @return list<Category>
     */
    public function categoriesOf(ResourceReference $resource): array
    {
        return $this->repository->categoriesWithIds(array_map(static fn (Assignment $assignment): string => $assignment->termId, $this->repository->assignments($resource, Assignment::CATEGORY)));
    }

    /**
     * The resources in a category, and in its whole branch unless told otherwise.
     *
     * @return list<ResourceReference>
     */
    public function categorizedIn(Category $category, bool $withDescendants = true, ?string $resourceKind = null): array
    {
        return $this->repository->resourcesWith(Assignment::CATEGORY, $withDescendants ? $this->repository->branchIds($category) : [$category->id], $resourceKind);
    }

    /**
     * Moves a category's children and resources to another, then removes it.
     *
     * @throws InvalidTaxonomy when the second is the first, or in its branch
     */
    public function mergeCategories(Category $from, Category $into): void
    {
        if ($from->id === $into->id || $from->isAncestorOf($into)) {
            throw InvalidTaxonomy::because('A category is not merged into itself or its own branch.');
        }

        // The tree is read once; each child moves in memory, and they are written together.
        $categories = $this->repository->categories();
        $moved = [];

        foreach ($categories as $category) {
            if ($category->parent()?->id === $from->id) {
                foreach ($this->moved($category, $into, $category->position(), $categories) as $changed) {
                    $moved[] = $changed;
                }
            }
        }

        $this->repository->save(...$moved);

        // Its translations go with it, by the database's cascade.
        $this->repository->reassign(Assignment::CATEGORY, $from->id, $into->id);
        $this->repository->remove($from);
    }

    /**
     * Moves a category, and its branch, among the categories given: the category then its descendants, changed.
     *
     * @param list<Category> $categories every category, parents before their children
     *
     * @return list<Category>
     *
     * @throws InvalidTaxonomy when the parent is in the branch, or the branch would go deeper than `Category::MAX_DEPTH`
     */
    private function moved(Category $category, ?Category $parent, int $position, array $categories): array
    {
        // Parents come before their children: each one follows a parent already moved.
        $descendants = [];
        $height = 0;

        foreach ($categories as $other) {
            if ($category->isAncestorOf($other)) {
                $descendants[] = $other;
                $height = max($height, $other->depth() - $category->depth());
            }
        }

        $category->moveTo($parent, $position, $height);

        foreach ($descendants as $descendant) {
            $descendant->followParent();
        }

        return [$category, ...$descendants];
    }

    /**
     * @param array<string, list<Category>> $children
     * @param array<string, string>         $names    by identifier
     *
     * @return list<CategoryNode>
     */
    private function nodes(array $children, string $parent, array $names): array
    {
        // The repository gives the categories by position, then key.
        return array_map(fn (Category $category): CategoryNode => new CategoryNode($category, $names[$category->id] ?? $category->key(), $this->nodes($children, $category->id, $names)), $children[$parent] ?? []);
    }

    /**
     * @throws InvalidTaxonomy when the text holds nothing to make a slug of
     */
    private function slugOf(string $text): Slug
    {
        try {
            return $this->slugger->slug($text);
        } catch (InvalidSlug $invalidSlug) {
            throw InvalidTaxonomy::because($invalidSlug->getMessage());
        }
    }
}
