<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Symfony\Component\Uid\Uuid;

/**
 * A category's name and description in one language.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_taxonomy_category_translation')]
#[ORM\UniqueConstraint(columns: ['category', 'locale'])]
final class CategoryTranslation
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Category::class)]
        #[ORM\JoinColumn(name: 'category', nullable: false, onDelete: 'CASCADE')]
        public readonly Category $category,
        #[ORM\Column(name: 'locale', length: 16)]
        public readonly string $locale,
        #[ORM\Column(name: 'name', length: 150)]
        private string $name,
        #[ORM\Column(name: 'description', type: 'text', nullable: true)]
        private ?string $description,
    ) {
    }

    public static function of(Category $category, string $locale, string $name, ?string $description = null): self
    {
        if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $locale)) {
            throw InvalidTaxonomy::because(\sprintf('A locale is a language, and maybe a region: "fr" or "fr_CA"; "%s" is not.', $locale));
        }

        $translation = new self(Uuid::v7()->toRfc4122(), $category, $locale, '', null);
        $translation->change($name, $description);

        return $translation;
    }

    public function change(string $name, ?string $description = null): void
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 150) {
            throw InvalidTaxonomy::because('A category name is 1 to 150 characters.');
        }

        $description = null === $description ? null : trim($description);
        $this->name = $name;
        $this->description = '' === $description ? null : $description;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
