<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * A product's name in one locale — *Dice set*, *Jeu de dés* —; its price, attributes and stock are the product's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_catalog_product_translation')]
#[ORM\UniqueConstraint(name: 'phil_commerce_catalog_product_translation_translatable_locale', columns: ['product', 'locale'])]
final class ProductTranslation implements Translation
{
    /** The locale, in the one spelling of `phil/locale-dimensions`. */
    #[ORM\Column(name: 'locale', length: Translation::LOCALE_LENGTH)]
    private readonly string $locale;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 255)]
    private string $name = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'product', referencedColumnName: 'sku', nullable: false, onDelete: 'CASCADE')]
        private readonly Product $translatable,
        Locale $locale,
    ) {
        $this->locale = $locale->value;
    }

    #[Override]
    public function locale(): Locale
    {
        return Locale::fromString($this->locale);
    }

    #[Override]
    public function translatable(): Product
    {
        return $this->translatable;
    }

    /**
     * The name in this locale; empty until it is written.
     */
    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because(\sprintf('The product %s has a name of 1 to 255 characters in %s.', $this->translatable->sku, $this->locale));
        }

        $this->name = $name;
    }
}
