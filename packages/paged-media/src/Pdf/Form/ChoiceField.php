<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;

/**
 * A choice among options: a drop-down list (a combo box), which may also take a value typed by the reader, or a list
 * box, which shows its options in its box.
 */
final readonly class ChoiceField
{
    /** @var non-empty-list<array{string, string}> each option's value, which the field takes, and its label */
    public array $options;

    /**
     * @param string                             $name     the field's name, unique in the document, without a period
     * @param list<string>|array<string, string> $options  the labels, or the labels by the value each gives
     * @param string|null                        $selected the value chosen when the document opens, if any
     * @param bool                               $combo    a drop-down list when true, a list box otherwise
     * @param bool                               $editable whether the reader may type a value of their own (combo box only)
     */
    public function __construct(
        public string $name,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        array $options,
        public ?string $selected = null,
        public bool $combo = true,
        public bool $editable = false,
        public float $fontSize = 10,
        public StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        public ?Color $color = null,
        public ?Color $border = null,
        public ?Color $background = null,
    ) {
        Check::name($name);
        Check::measure($width, 'width');
        Check::measure($height, 'height');
        Check::measure($fontSize, 'font size');

        $pairs = [];
        $labelled = !array_is_list($options);

        foreach ($options as $value => $label) {
            $pairs[] = [$labelled ? (string) $value : $label, Check::text($label, false, 'choice label')];
        }

        $values = array_column($pairs, 0);

        if ([] === $pairs || \count(array_unique($values)) !== \count($values)) {
            throw InvalidDocument::because(\sprintf('The choice field "%s" has at least one option, each with its own value.', $name));
        }

        if ($editable && !$combo) {
            throw InvalidDocument::because(\sprintf('The choice field "%s" is a list box; only a combo box takes a typed value.', $name));
        }

        if (null !== $selected && !\in_array($selected, $values, true)) {
            if (!$editable) {
                throw InvalidDocument::because(\sprintf('The choice field "%s" chooses one of its values (%s); "%s" is not.', $name, implode(', ', $values), $selected));
            }

            Check::text($selected, false, 'typed choice');
        }

        $this->options = $pairs;
    }

    /**
     * What the field shows: the selected option's label, a typed value as it is, or nothing.
     */
    public function shown(): string
    {
        foreach ($this->options as [$value, $label]) {
            if ($value === $this->selected) {
                return $label;
            }
        }

        return $this->selected ?? '';
    }
}
