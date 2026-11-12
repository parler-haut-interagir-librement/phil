<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;

/**
 * A record whose title and instructions are translated; its servings are the same in every locale.
 */
#[ORM\Entity]
#[ORM\Table(name: 'recipe')]
class Recipe implements Translatable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public int $servings = 4,
    ) {
    }

    #[Override]
    public static function translationClass(): string
    {
        return RecipeTranslation::class;
    }

    #[Override]
    public static function translatedFields(): array
    {
        return ['title', 'instructions'];
    }

    #[Override]
    public function newTranslation(Locale $locale): RecipeTranslation
    {
        return new RecipeTranslation($this, $locale);
    }
}
