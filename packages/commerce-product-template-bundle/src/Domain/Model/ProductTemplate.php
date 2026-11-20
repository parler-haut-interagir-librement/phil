<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\TemplateArray;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A product template as the store keeps it: its definition — axes, faces, pricing, exclusions, SKU pattern —, what
 * the catalogue makes of its products — their kind and tax category —, and the options of the core of commerce its
 * products are sold with.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_product_template')]
final class ProductTemplate
{
    /** @var array<array-key, mixed> */
    #[ORM\Column(name: 'definition', type: 'json')]
    private array $definition;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(name: 'options', type: 'json')]
    private array $options = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 32)]
        public readonly string $code,
        Template $template,
        #[ORM\Column(name: 'kind', length: 16, enumType: ProductKind::class)]
        private ProductKind $kind,
        #[ORM\Column(name: 'tax_category', length: 64)]
        private string $taxCategory,
    ) {
        $this->definition = TemplateArray::toArray($template);
    }

    public static function create(Template $template, ProductKind $kind, string $taxCategory): self
    {
        return new self($template->code, $template, $kind, self::checkedCategory($taxCategory));
    }

    public function template(): Template
    {
        return TemplateArray::fromArray($this->definition);
    }

    /**
     * A new definition of the same template, for the products generated from now on.
     */
    public function redefine(Template $template): void
    {
        if ($template->code !== $this->code) {
            throw InvalidValue::because(\sprintf('The template %s is redefined under its own code, not %s.', $this->code, $template->code));
        }

        $this->definition = TemplateArray::toArray($template);
    }

    public function kind(): ProductKind
    {
        return $this->kind;
    }

    public function taxCategory(): string
    {
        return $this->taxCategory;
    }

    /**
     * What its products are for the catalogue: their kind, for the products made from now on, and their tax category.
     */
    public function classify(ProductKind $kind, string $taxCategory): void
    {
        $this->kind = $kind;
        $this->taxCategory = self::checkedCategory($taxCategory);
    }

    /**
     * Replaces the options its products are sold with.
     */
    public function declareOptions(TemplateOption ...$options): void
    {
        $this->options = TemplateOption::listToArray(array_values($options));
    }

    /**
     * @return list<TemplateOption> in the order declared
     */
    public function options(): array
    {
        return array_map(TemplateOption::fromArray(...), $this->options);
    }

    private static function checkedCategory(string $taxCategory): string
    {
        if ('' === $taxCategory || \strlen($taxCategory) > 64) {
            throw InvalidValue::because('A tax category holds 1 to 64 characters.');
        }

        return $taxCategory;
    }
}
