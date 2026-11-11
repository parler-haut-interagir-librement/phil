<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Ujo\Block;

/**
 * Attributes applied to a block, as UJO keeps them: the identifier as the `anchor` tune, `text-align` as the
 * `alignment` tune, the position, offsets and size, and the classes `wrapLeft` and `wrapRight`, as the `layout` tune.
 * The other classes become a `container` around the block, for a look that declares them; the other properties and
 * attributes, which UJO has no place for, are left out.
 *
 * @internal
 */
final class Layout
{
    private const string LENGTH = '/^(?:-?\d{1,4}(?:\.\d{1,3})?(?:px|em|rem|%|mm|cm|in|pt|ch|vw|vh)|0|auto)$/D';

    private const array SIDES = ['top', 'right', 'bottom', 'left', 'width', 'height'];

    public static function apply(Block $block, Attributes $attributes, Context $context, bool $classes = true): Block
    {
        $tunes = $block->tunes;
        $alignment = $attributes->styles['text-align'] ?? '';
        $layout = self::layout($attributes);

        if (1 === preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $attributes->id)) {
            $tunes['anchor'] = ['anchor' => $attributes->id];
            $context->anchors->reserve($attributes->id);
        }

        if (\in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
            $tunes['alignment'] = ['alignment' => $alignment];
        }

        if ([] !== $layout) {
            $tunes['layout'] = $layout;
        }

        $names = $classes ? self::classes($attributes) : [];
        $block = $block->withTunes($tunes);

        return [] === $names ? $block : $context->block('container', ['classes' => $names, 'blocks' => [$block]]);
    }

    /**
     * The classes that are names a look may declare, `wrapLeft` and `wrapRight` left out.
     *
     * @return list<string>
     */
    public static function classes(Attributes $attributes): array
    {
        return array_values(array_filter($attributes->classes, static fn (string $class): bool => 1 === preg_match('/^[A-Za-z0-9_-]{1,64}$/', $class) && !\in_array($class, ['wrapLeft', 'wrapRight'], true)));
    }

    /**
     * @return array<string, int|string>
     */
    private static function layout(Attributes $attributes): array
    {
        $layout = [];
        $position = $attributes->styles['position'] ?? '';
        $zIndex = filter_var($attributes->styles['z-index'] ?? '', \FILTER_VALIDATE_INT, ['options' => ['min_range' => -10, 'max_range' => 100]]);

        if (\in_array($position, ['absolute', 'relative'], true)) {
            $layout['position'] = $position;
        }

        foreach (self::SIDES as $side) {
            $value = $attributes->styles[$side] ?? '';

            if (1 === preg_match(self::LENGTH, $value)) {
                $layout[$side] = $value;
            }
        }

        if (false !== $zIndex) {
            $layout['zIndex'] = $zIndex;
        }

        foreach (['left' => 'wrapLeft', 'right' => 'wrapRight'] as $side => $class) {
            if ($attributes->has($class)) {
                $layout['wrap'] = $side;
            }
        }

        return $layout;
    }
}
