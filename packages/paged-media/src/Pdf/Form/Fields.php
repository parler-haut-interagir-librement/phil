<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Closure;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\Number;

/**
 * Turns a form field into the widgets the writer places: their dictionaries and their appearance streams, drawn so that
 * a reader shows the field as it is without generating anything, and so that the field can be flattened into the page.
 *
 * @internal
 */
final class Fields
{
    private const int READ_ONLY = 1;

    private const int REQUIRED = 2;

    private const int MULTILINE = 4096;

    private const int NO_TOGGLE_TO_OFF = 16384;

    private const int RADIO = 32768;

    private const int COMBO = 131072;

    private const int EDIT = 262144;

    /** The space between a box's edge and its text, in points. */
    private const float PADDING = 2;

    /** The distance between two lines of text, in sizes. */
    private const float LEADING = 1.15;

    /** Bézier control distance of a quarter circle, in radii. */
    private const float KAPPA = 0.5523;

    /**
     * @param string|null             $font    the resource name of the field's font, for a text or a choice field
     * @param Closure(string): string $operand a text written as the operand of `Tj` in that font
     *
     * @return array{list<Widget>, string|null} the widgets, and the entries of the parent field of a radio group
     */
    public static function widgets(TextField|CheckBox|RadioGroup|ChoiceField $field, ?string $font, Closure $operand): array
    {
        return match (true) {
            $field instanceof TextField => [[self::text($field, (string) $font, $operand)], null],
            $field instanceof ChoiceField => [[self::choice($field, (string) $font, $operand)], null],
            $field instanceof CheckBox => [[self::checkBox($field)], null],
            default => self::radio($field),
        };
    }

    /**
     * A PDF name: the bytes outside the printable range and the delimiters written `#xx`.
     */
    public static function name(string $value): string
    {
        return '/'.preg_replace_callback('/[^!-~]|[#%\/()<>\[\]{}]/', static fn (array $byte): string => \sprintf('#%02X', \ord($byte[0])), $value);
    }

    /**
     * @param Closure(string): string $operand
     */
    private static function text(TextField $field, string $font, Closure $operand): Widget
    {
        $lines = $field->multiline ? self::lines($field->value, $field->width, $field->height, $field->fontSize, $field->font) : [$field->value];
        $appearance = self::frame($field->width, $field->height, $field->border, $field->background)
            .self::textLines($lines, $field->width, $field->height, $field->fontSize, $font, $field->color, $operand, $field->multiline);
        $entries = '/FT /Tx'.self::common($field->name, $field->tooltip, self::flags($field->readOnly, $field->required) | ($field->multiline ? self::MULTILINE : 0))
            .' /V '.Metadata::text($field->value)
            .' /DA '.self::defaultAppearance($font, $field->fontSize, $field->color)
            .(null === $field->maxLength ? '' : ' /MaxLen '.$field->maxLength)
            .self::characteristics($field->border, $field->background);

        return new Widget($field->x, $field->y, $field->width, $field->height, ['' => $appearance], null, $entries, $font);
    }

    /**
     * @param Closure(string): string $operand
     */
    private static function choice(ChoiceField $field, string $font, Closure $operand): Widget
    {
        $appearance = self::frame($field->width, $field->height, $field->border, $field->background);

        if ($field->combo) {
            $appearance .= self::textLines([$field->shown()], $field->width, $field->height, $field->fontSize, $font, $field->color, $operand, false);
        } else {
            $appearance .= self::listBox($field, $font, $operand);
        }

        $options = [];

        foreach ($field->options as [$value, $label]) {
            $options[] = $value === $label ? Metadata::text($label) : '['.Metadata::text($value).' '.Metadata::text($label).']';
        }

        $selected = null === $field->selected ? '' : ' /V '.Metadata::text($field->selected);
        $index = array_search($field->selected, array_column($field->options, 0), true);
        $flags = self::flags($field->readOnly, $field->required) | ($field->combo ? self::COMBO : 0) | ($field->editable ? self::EDIT : 0);
        $entries = '/FT /Ch'.self::common($field->name, $field->tooltip, $flags)
            .' /Opt ['.implode(' ', $options).']'
            .$selected
            .(\is_int($index) && !$field->combo ? ' /I ['.$index.']' : '')
            .' /DA '.self::defaultAppearance($font, $field->fontSize, $field->color)
            .self::characteristics($field->border, $field->background);

        return new Widget($field->x, $field->y, $field->width, $field->height, ['' => $appearance], null, $entries, $font);
    }

    private static function checkBox(CheckBox $field): Widget
    {
        $frame = self::frame($field->size, $field->size, $field->border, $field->background);
        $s = $field->size;
        $mark = \sprintf(
            "q %s %s w 1 J 1 j %s %s m %s %s l %s %s l S Q\n",
            ($field->color ?? Color::black())->operator(true),
            Number::format($s / 8),
            Number::format($s * 0.22),
            Number::format($s * 0.52),
            Number::format($s * 0.42),
            Number::format($s * 0.28),
            Number::format($s * 0.78),
            Number::format($s * 0.74),
        );
        $state = $field->checked ? $field->exportValue : 'Off';
        $entries = '/FT /Btn'.self::common($field->name, $field->tooltip, self::flags($field->readOnly, $field->required))
            .' /V '.self::name($state)
            .self::characteristics($field->border, $field->background);

        return new Widget($field->x, $field->y, $s, $s, [$field->exportValue => $frame.$mark, 'Off' => $frame], $state, $entries, null);
    }

    /**
     * @return array{list<Widget>, string}
     */
    private static function radio(RadioGroup $field): array
    {
        $widgets = [];

        foreach ($field->buttons as $button) {
            $radius = $button->size / 2;
            $frame = ($field->background instanceof Color ? \sprintf("q %s %s f Q\n", $field->background->operator(false), self::circle($radius, $radius, $radius)) : '')
                .($field->border instanceof Color ? \sprintf("q %s 1 w %s S Q\n", $field->border->operator(true), self::circle($radius, $radius, $radius - 0.5)) : '');
            $dot = \sprintf("q %s %s f Q\n", ($field->color ?? Color::black())->operator(false), self::circle($radius, $radius, $radius / 2));
            $widgets[] = new Widget($button->x, $button->y, $button->size, $button->size, [$button->value => $frame.$dot, 'Off' => $frame], $button->value === $field->selected ? $button->value : 'Off', self::characteristics($field->border, $field->background), null);
        }

        $parent = '/FT /Btn'.self::common($field->name, $field->tooltip, self::flags($field->readOnly, $field->required) | self::RADIO | self::NO_TOGGLE_TO_OFF)
            .' /V '.self::name($field->selected ?? 'Off');

        return [$widgets, $parent];
    }

    /**
     * The options of a list box, one per line from the top, the selected one highlighted.
     *
     * @param Closure(string): string $operand
     */
    private static function listBox(ChoiceField $field, string $font, Closure $operand): string
    {
        $line = $field->fontSize * self::LEADING;
        $top = $field->height - self::PADDING;
        $content = "/Tx BMC\nq ".self::clip($field->width, $field->height)."\n";

        foreach ($field->options as [$value, $label]) {
            if ($top - $line < 0) {
                break;
            }

            if ($value === $field->selected) {
                $content .= \sprintf("q 0.6 0.75 0.875 rg 1 %s %s %s re f Q\n", Number::format($top - $line), Number::format($field->width - 2), Number::format($line));
            }

            $content .= self::show($font, $field->fontSize, $field->color, $operand($label), self::PADDING, $top - $field->fontSize);
            $top -= $line;
        }

        return $content."Q\nEMC\n";
    }

    /**
     * The lines of a multiline field: each paragraph wrapped to the box, as many as the box holds.
     *
     * @return list<string>
     */
    private static function lines(string $value, float $width, float $height, float $size, StandardFont|TrueTypeFont $font): array
    {
        $lines = [];

        $paragraphs = preg_split('/\r\n|\r|\n/', $value);

        foreach (false === $paragraphs ? [] : $paragraphs as $paragraph) {
            array_push($lines, ...Document::wrap($paragraph, max($width - 2 * self::PADDING, 1), $size, $font));
        }

        return \array_slice($lines, 0, max(1, (int) floor(($height - 2 * self::PADDING) / ($size * self::LEADING))));
    }

    /**
     * @param list<string>            $lines
     * @param Closure(string): string $operand
     */
    private static function textLines(array $lines, float $width, float $height, float $size, string $font, ?Color $color, Closure $operand, bool $fromTop): string
    {
        $content = "/Tx BMC\nq ".self::clip($width, $height)."\n";
        // One line sits in the middle of the box, its capitals centred; several start under the top edge.
        $baseline = $fromTop ? $height - self::PADDING - $size : ($height - $size * 0.7) / 2;

        foreach ($lines as $line) {
            if ('' !== $line) {
                $content .= self::show($font, $size, $color, $operand($line), self::PADDING, $baseline);
            }

            $baseline -= $size * self::LEADING;
        }

        return $content."Q\nEMC\n";
    }

    private static function show(string $font, float $size, ?Color $color, string $operand, float $x, float $y): string
    {
        return \sprintf("BT /%s %s Tf %s %s %s Td %s Tj ET\n", $font, Number::format($size), ($color ?? Color::black())->operator(false), Number::format($x), Number::format($y), $operand);
    }

    private static function clip(float $width, float $height): string
    {
        return \sprintf('1 1 %s %s re W n', Number::format($width - 2), Number::format($height - 2));
    }

    private static function frame(float $width, float $height, ?Color $border, ?Color $background): string
    {
        return ($background instanceof Color ? \sprintf("q %s 0 0 %s %s re f Q\n", $background->operator(false), Number::format($width), Number::format($height)) : '')
            .($border instanceof Color ? \sprintf("q %s 1 w 0.5 0.5 %s %s re S Q\n", $border->operator(true), Number::format($width - 1), Number::format($height - 1)) : '');
    }

    /**
     * A circle as four Bézier curves.
     */
    private static function circle(float $x, float $y, float $r): string
    {
        $k = $r * self::KAPPA;
        $f = Number::format(...);

        return \sprintf(
            '%s %s m %s %s %s %s %s %s c %s %s %s %s %s %s c %s %s %s %s %s %s c %s %s %s %s %s %s c',
            $f($x + $r),
            $f($y),
            $f($x + $r),
            $f($y + $k),
            $f($x + $k),
            $f($y + $r),
            $f($x),
            $f($y + $r),
            $f($x - $k),
            $f($y + $r),
            $f($x - $r),
            $f($y + $k),
            $f($x - $r),
            $f($y),
            $f($x - $r),
            $f($y - $k),
            $f($x - $k),
            $f($y - $r),
            $f($x),
            $f($y - $r),
            $f($x + $k),
            $f($y - $r),
            $f($x + $r),
            $f($y - $k),
            $f($x + $r),
            $f($y),
        );
    }

    private static function flags(bool $readOnly, bool $required): int
    {
        return ($readOnly ? self::READ_ONLY : 0) | ($required ? self::REQUIRED : 0);
    }

    private static function common(string $name, ?string $tooltip, int $flags): string
    {
        return ' /T '.Metadata::text($name).(null === $tooltip ? '' : ' /TU '.Metadata::text($tooltip)).(0 === $flags ? '' : ' /Ff '.$flags);
    }

    private static function defaultAppearance(string $font, float $size, ?Color $color): string
    {
        return '(/'.$font.' '.Number::format($size).' Tf '.($color ?? Color::black())->operator(false).')';
    }

    /**
     * The colours a reader uses when it draws the field again: `/MK`, when the field has any.
     */
    private static function characteristics(?Color $border, ?Color $background): string
    {
        $components = static fn (Color $color): string => '['.substr($color->operator(false), 0, (int) strrpos($color->operator(false), ' ')).']';
        $entries = ($border instanceof Color ? '/BC '.$components($border).' ' : '').($background instanceof Color ? '/BG '.$components($background).' ' : '');

        return '' === $entries ? '' : ' /MK << '.$entries.'>>';
    }
}
