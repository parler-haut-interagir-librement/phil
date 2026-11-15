<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Override;
use Phil\Ujo\Html\HtmlFilter;

/**
 * The checkboxes `phil/ujo` renders at the start of checklist items, written as text — `☑` for a checked item, `☐`
 * for one that is not —: below the level `all`, where no `input` is kept, the state of each item is still read, by
 * eyes and by screen readers, which name both characters.
 */
final readonly class CheckboxText implements HtmlFilter
{
    public const string CHECKED = "\u{2611} ";

    public const string UNCHECKED = "\u{2610} ";

    #[Override]
    public function filter(string $html): string
    {
        return str_contains($html, '<input') ? strtr($html, [
            '<input type="checkbox" disabled checked>' => self::CHECKED,
            '<input type="checkbox" disabled>' => self::UNCHECKED,
        ]) : $html;
    }
}
