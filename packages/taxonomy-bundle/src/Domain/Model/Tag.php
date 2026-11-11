<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Symfony\Component\Uid\Uuid;

/**
 * A tag: a free word people attach to resources, once whatever its case or accents — its slug is its key, made by
 * the slugger of the taxonomy.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_taxonomy_tag')]
#[ORM\UniqueConstraint(columns: ['slug'])]
final class Tag
{
    /**
     * The longest name, in characters.
     */
    public const int MAX_LENGTH = 100;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'name', length: 100)]
        private string $name,
        #[ORM\Column(name: 'slug', length: 128)]
        private string $slug,
    ) {
    }

    /**
     * @throws InvalidTaxonomy when the name is empty or longer than 100 characters, or the slug longer than 128
     */
    public static function named(string $name, Slug $slug): self
    {
        [$name, $slug] = self::checked($name, $slug);

        return new self(Uuid::v7()->toRfc4122(), $name, $slug);
    }

    /**
     * @throws InvalidTaxonomy when the name is empty or longer than 100 characters, or the slug longer than 128
     */
    public function rename(string $name, Slug $slug): void
    {
        [$this->name, $this->slug] = self::checked($name, $slug);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * @return array{string, string}
     */
    private static function checked(string $name, Slug $slug): array
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::MAX_LENGTH) {
            throw InvalidTaxonomy::because('A tag is 1 to 100 characters.');
        }

        if (\strlen($slug->value) > 128) {
            throw InvalidTaxonomy::because(\sprintf('The slug of a tag is 128 characters or fewer; "%s" is not.', $slug));
        }

        return [$name, $slug->value];
    }
}
