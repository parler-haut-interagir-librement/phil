<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Style\InitialsStyle;
use Phil\Avatar\Style\PixelsStyle;
use Phil\Avatar\Style\ShapesStyle;

/**
 * Draws avatars as SVG documents from a seed: the same seed and options give the same bytes, on every machine and in
 * every process, with nothing stored and no service called.
 *
 * An avatar that has an accessible name is an image labelled by it (`role="img"`, `aria-label` and `<title>`); one
 * without is hidden from assistive technologies. The generator keeps no state between calls.
 */
final readonly class AvatarGenerator
{
    /** @var array<string, AvatarStyle> */
    private array $styles;

    /**
     * @param iterable<AvatarStyle> $styles the styles beside the package's — initials, shapes and pixels —; one of the
     *                                      same name replaces it
     *
     * @throws InvalidAvatar when a style's name is not one
     */
    public function __construct(iterable $styles = [])
    {
        $known = [];

        foreach ([new InitialsStyle(), new ShapesStyle(), new PixelsStyle(), ...$styles] as $style) {
            if (1 !== preg_match('/^[a-z0-9-]{1,32}$/D', $style->name())) {
                throw InvalidAvatar::because('The name of a style is 1 to 32 lowercase letters, digits or "-".');
            }

            $known[$style->name()] = $style;
        }

        $this->styles = $known;
    }

    /**
     * The avatar of a seed, as an SVG document.
     *
     * @throws InvalidAvatar when the seed is not one, or the style unknown
     */
    public function svg(Seed|string $seed, AvatarOptions $options = new AvatarOptions()): string
    {
        $seed = $seed instanceof Seed ? $seed : new Seed($seed);
        $style = $this->styles[$options->style] ?? throw InvalidAvatar::because(\sprintf('The styles are %s.', implode(', ', array_keys($this->styles))));
        $canvas = new Canvas($options->palette, Initials::of($options->name ?? '', $options->maxLetters));
        $style->draw($canvas, Random::of($seed, $style->name()));

        $name = $options->accessibleName();
        $accessibility = null === $name
            ? ' aria-hidden="true" focusable="false"'
            : \sprintf(' role="img" aria-label="%s"', Canvas::escape($name));
        $title = null === $name ? '' : \sprintf('<title>%s</title>', Canvas::escape($name));
        [$defs, $open, $close] = $this->outline($seed, $options);

        return \sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%1$d" height="%1$d"%2$s>%3$s%4$s%5$s%6$s%7$s</svg>',
            $options->size,
            $accessibility,
            $title,
            $defs,
            $open,
            $canvas->body(),
            $close,
        );
    }

    /**
     * The avatar of a seed as a `data:` URI, its document percent-encoded, for the `src` of an `<img>`, whose `alt`
     * then names it.
     *
     * @throws InvalidAvatar when the seed is not one, or the style unknown
     */
    public function dataUri(Seed|string $seed, AvatarOptions $options = new AvatarOptions()): string
    {
        return 'data:image/svg+xml,'.rawurlencode($this->svg($seed, $options));
    }

    /**
     * The names of the styles the generator knows.
     *
     * @return list<string>
     */
    public function styles(): array
    {
        return array_keys($this->styles);
    }

    /**
     * The clip of the outline, under an identifier drawn from the seed and options: two avatars on a page share one
     * only when they are the same avatar, whose clips are the same.
     *
     * @return array{string, string, string}
     */
    private function outline(Seed $seed, AvatarOptions $options): array
    {
        $shape = $options->shape;

        if (Shape::Square === $shape) {
            return ['', '', ''];
        }

        $id = 'phil-avatar-'.substr(hash('sha256', $seed->value."\0".$options->style."\0".$shape->value), 0, 16);
        $clip = Shape::Circle === $shape ? '<circle cx="50" cy="50" r="50"/>' : '<rect width="100" height="100" rx="16"/>';

        return [\sprintf('<defs><clipPath id="%s">%s</clipPath></defs>', $id, $clip), \sprintf('<g clip-path="url(#%s)">', $id), '</g>'];
    }
}
