<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\ScopedSluggableEntity;
use Phil\DoctrineBehaviours\TranslationEntity;
use Phil\DoctrineBehaviours\VersionableEntity;
use Phil\LocaleDimensions\Locale;

/**
 * One locale of a recipe, with a slug unique within its locale and versions of its own; the scope of the slug can be
 * changed by a test.
 */
#[ORM\Entity]
#[ORM\Table(name: 'recipe_translation')]
#[ORM\UniqueConstraint(columns: ['locale', 'slug'])]
class RecipeTranslation implements Translation, Sluggable, Versionable
{
    use ScopedSluggableEntity;
    use TranslationEntity;
    use VersionableEntity;

    /** @var list<string> */
    public array $scope = [Translation::LOCALE_FIELD];

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    public string $id;

    #[ORM\Column]
    public string $title = '';

    #[ORM\Column(nullable: true)]
    public ?string $instructions = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Recipe::class)]
        #[ORM\JoinColumn(name: 'recipe_id', nullable: false, onDelete: 'CASCADE')]
        private Recipe $translatable,
        Locale $locale,
    ) {
        $this->id = $translatable->id.'/'.$locale->value;
        $this->writtenIn($locale);
    }

    #[Override]
    public function translatable(): Recipe
    {
        return $this->translatable;
    }

    #[Override]
    public function slugSource(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function slugScope(): array
    {
        return $this->scope;
    }

    #[Override]
    public function versionSubjectId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function versionedState(): array
    {
        return ['title' => $this->title, 'instructions' => $this->instructions];
    }

    /**
     * @param array<string, mixed> $state
     */
    #[Override]
    public function applyVersionedState(array $state): void
    {
        $this->title = \is_string($state['title'] ?? null) ? $state['title'] : '';
        $this->instructions = \is_string($state['instructions'] ?? null) ? $state['instructions'] : null;
    }
}
