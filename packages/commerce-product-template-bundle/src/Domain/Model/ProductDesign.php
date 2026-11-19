<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\DesignArray;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A design as the store keeps it: its definition — languages, artworks, templates and values allowed —, the options of
 * its own its products are sold with, which replace a template's option of the same code, and its print layout: what
 * each print file holds over the artwork of its zone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_product_design')]
final class ProductDesign
{
    /** The most elements a print layout holds, so that composing an order line stays bounded. */
    public const int MAX_PRINT_ELEMENTS = 64;

    /** @var array<array-key, mixed> */
    #[ORM\Column(name: 'definition', type: 'json')]
    private array $definition;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(name: 'options', type: 'json')]
    private array $options = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(name: 'print_layout', type: 'json', options: ['default' => '[]'])]
    private array $printLayout = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 32)]
        public readonly string $code,
        Design $design,
    ) {
        $this->definition = DesignArray::toArray($design);
    }

    public static function create(Design $design): self
    {
        return new self($design->code, $design);
    }

    public function design(): Design
    {
        return DesignArray::fromArray($this->definition);
    }

    /**
     * A new definition of the same design, for the products generated from now on.
     */
    public function redefine(Design $design): void
    {
        if ($design->code !== $this->code) {
            throw InvalidValue::because(\sprintf('The design %s is redefined under its own code, not %s.', $this->code, $design->code));
        }

        $this->definition = DesignArray::toArray($design);
    }

    /**
     * Replaces the options of its own its products are sold with.
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

    /**
     * Replaces its print layout: the elements printed over the artworks, in the order they are drawn.
     */
    public function declarePrintLayout(PrintElement ...$elements): void
    {
        if (\count($elements) > self::MAX_PRINT_ELEMENTS) {
            throw InvalidValue::because(\sprintf('A print layout holds at most %d elements.', self::MAX_PRINT_ELEMENTS));
        }

        $this->printLayout = array_values(array_map(static fn (PrintElement $element): array => $element->toArray(), $elements));
    }

    /**
     * @return list<PrintElement> in the order they are drawn
     */
    public function printLayout(): array
    {
        return array_map(PrintElement::fromArray(...), $this->printLayout);
    }
}
