<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

/**
 * One line of a production order: the units of a product that are printed alike — its SKU, how many, its language
 * version, its value on each axis (the size, the colour, the version) with the supplier's name, the value of each
 * option, the dice one character per die, and its print files.
 *
 * Units whose draws differ are lines of their own: each loot box has its own objects.
 */
final readonly class ProductionLine
{
    /**
     * @param array<string, array{code: string, label: string, supplier: string|null}> $values  by axis code, in the template's order
     * @param array<string, string>                                                    $options the value of each option, by option code: a text, a choice's code, a draw
     * @param array<string, string>                                                    $dice    the dice of each dice option, one character per die, by option code
     * @param list<PrintFile>                                                          $files
     */
    public function __construct(
        public string $sku,
        public int $quantity,
        public ?string $language,
        public array $values,
        public array $options,
        public array $dice,
        public array $files,
    ) {
    }

    /**
     * The same line, one unit more.
     */
    public function plusOne(): self
    {
        return new self($this->sku, $this->quantity + 1, $this->language, $this->values, $this->options, $this->dice, $this->files);
    }

    /**
     * @return array{sku: string, quantity: int, language: string|null, values: array<string, array{code: string, label: string, supplier: string|null}>, options: array<string, string>, dice: array<string, string>, files: list<array{face: string, zone: string, ink: string, width: float, height: float, dpi: int, path: string}>}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'language' => $this->language,
            'values' => $this->values,
            'options' => $this->options,
            'dice' => $this->dice,
            'files' => array_map(static fn (PrintFile $file): array => $file->toArray(), $this->files),
        ];
    }
}
