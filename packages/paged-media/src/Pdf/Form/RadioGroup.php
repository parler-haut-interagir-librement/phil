<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\Color;

/**
 * Circles of which a reader chooses one: a size, a class, a delivery. The group is one field, its value the chosen
 * button's, or `Off` while none is chosen; its buttons are drawn on the page the group is added to.
 */
final readonly class RadioGroup
{
    /** @var non-empty-list<RadioButton> */
    public array $buttons;

    /**
     * @param string            $name       the field's name, unique in the document, without a period
     * @param list<RadioButton> $buttons    at least one, each with its own value
     * @param string|null       $selected   the value chosen when the document opens, if any
     * @param Color|null        $color      the dot's colour; black when none is given
     * @param Color|null        $border     each circle's outline, none when none is given
     * @param Color|null        $background each circle's fill, none when none is given
     */
    public function __construct(
        public string $name,
        array $buttons,
        public ?string $selected = null,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        public ?Color $color = null,
        public ?Color $border = null,
        public ?Color $background = null,
    ) {
        Check::name($name);

        if ([] === $buttons) {
            throw InvalidDocument::because(\sprintf('The radio group "%s" has at least one button.', $name));
        }

        $values = array_map(static fn (RadioButton $button): string => $button->value, $buttons);

        if (\count(array_unique($values)) !== \count($values)) {
            throw InvalidDocument::because(\sprintf('Each button of the radio group "%s" has its own value.', $name));
        }

        if (null !== $selected && !\in_array($selected, $values, true)) {
            throw InvalidDocument::because(\sprintf('The radio group "%s" chooses one of its values (%s); "%s" is not.', $name, implode(', ', $values), $selected));
        }

        $this->buttons = $buttons;
    }
}
