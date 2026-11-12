<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

/**
 * Proposes the `Image` and `Picture` recipes in place of the `img` and `picture` elements a Twig template writes by
 * hand, and never more than it understands: an element is proposed only when each of its attributes has its place in
 * the recipe and its text alternative and size are given; any other is left as it is, with what stands in the way.
 *
 * What it reads: the attributes of an `img`, quoted or not, their values written or printed by `{{ … }}`; a `picture`
 * of `source` elements and one `img`. A tag that holds a `{% … %}` block or prints its attributes whole, a `picture`
 * holding anything else, a size in another unit than pixels, are left for a person to rewrite. The defaults of the
 * recipe, `loading="lazy"` and `decoding="async"`, are dropped; `fetchpriority="high"` becomes `priority`, which also
 * preloads the image, and `fetchpriority="low"` becomes `deferred`.
 */
final readonly class ImageMigration
{
    /** A Twig expression printed, a block or a comment, inside a tag. */
    private const string TWIG = '\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\}';

    /** A quoted value, which may hold a Twig expression whose strings use the same quote. */
    private const string QUOTED = '"(?:[^"{]|\{\{.*?\}\}|\{)*"|\'(?:[^\'{]|\{\{.*?\}\}|\{)*\'';

    /** What a tag holds up to its `>`: characters, quoted values, Twig, and a brace that opens none of them. */
    private const string TAG = '(?:[^>"\'{]|'.self::QUOTED.'|'.self::TWIG.'|\{)*';

    /** The attributes the recipe passes to its `img` as they are, as well as `class`. */
    private const string KEPT = '/^(class|id|title|crossorigin|referrerpolicy|itemprop|draggable|(data|aria)-[a-z0-9-]+)$/';

    /** The keys of a source of `Picture`. */
    private const array SOURCE_KEYS = ['type', 'srcset', 'sizes', 'media', 'width', 'height'];

    /**
     * The `img` and `picture` elements of a template, in their order, each with its proposal or what stands in its way.
     *
     * @return list<ImageMigrationFinding>
     */
    public function find(string $template): array
    {
        $findings = [];
        // The comments of Twig and of HTML, blanked to the same length: what they mention is no element.
        $masked = (string) preg_replace_callback('~\{#.*?#\}|<!--.*?-->~s', $this->blank(...), $template);

        preg_match_all('~<picture\b'.self::TAG.'>(.*?)</picture\s*>~is', $masked, $pictures, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($pictures as $picture) {
            $offset = $picture[0][1];
            $findings[] = $this->picture($template, substr($template, $offset, \strlen($picture[0][0])), $offset, substr($template, $picture[1][1], \strlen($picture[1][0])));
        }

        // The pictures blanked in turn: their images are theirs.
        $masked = (string) preg_replace_callback('~<picture\b'.self::TAG.'>.*?</picture\s*>~is', $this->blank(...), $masked);
        preg_match_all('~<img\b('.self::TAG.')>~is', $masked, $images, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($images as $image) {
            $offset = $image[0][1];
            [$props, $notes] = $this->image(substr($template, $image[1][1], \strlen($image[1][0])));
            $findings[] = new ImageMigrationFinding(
                $this->line($template, $offset),
                $offset,
                substr($template, $offset, \strlen($image[0][0])),
                null === $props ? null : '<twig:Image '.implode(' ', $props).' />',
                $notes,
            );
        }

        usort($findings, static fn (ImageMigrationFinding $one, ImageMigrationFinding $other): int => $one->offset <=> $other->offset);

        return $findings;
    }

    /**
     * The template with the proposals of the findings in place of their elements; the elements left as they are stay.
     *
     * @param list<ImageMigrationFinding> $findings Findings of this very template, from `find()`
     */
    public function apply(string $template, array $findings): string
    {
        usort($findings, static fn (ImageMigrationFinding $one, ImageMigrationFinding $other): int => $other->offset <=> $one->offset);

        foreach ($findings as $finding) {
            if (null !== $finding->proposal && substr($template, $finding->offset, \strlen($finding->original)) === $finding->original) {
                $template = substr_replace($template, $finding->proposal, $finding->offset, \strlen($finding->original));
            }
        }

        return $template;
    }

    private function picture(string $template, string $element, int $offset, string $inside): ImageMigrationFinding
    {
        $line = $this->line($template, $offset);

        preg_match_all('~<source\b('.self::TAG.')>~is', $inside, $sources);
        $images = preg_match_all('~<img\b('.self::TAG.')>~is', $inside, $image);
        $rest = trim((string) preg_replace('~<(source|img)\b'.self::TAG.'>~is', '', $inside));

        if (1 !== $images || '' !== $rest) {
            return new ImageMigrationFinding($line, $offset, $element, null, ['A picture holds its sources and one img, and nothing else: rewrite it by hand.']);
        }

        [$props, $notes] = $this->image($image[1][0]);
        $blocked = null === $props;
        $entries = [];

        foreach ($sources[1] as $source) {
            $attributes = $this->attributes($source);

            if (null === $attributes) {
                $notes[] = 'A source holds a Twig block or prints its attributes: rewrite it by hand.';
                $blocked = true;

                continue;
            }

            $entry = [];

            foreach ($attributes as $name => $value) {
                if (!\in_array($name, self::SOURCE_KEYS, true)) {
                    $notes[] = \sprintf('The attribute %s of a source has no place in the recipe.', $name);
                    $blocked = true;

                    continue;
                }

                $expression = $this->expression($value);

                if (null === $expression) {
                    $notes[] = \sprintf('The %s of a source holds a Twig block or a double quote: rewrite it by hand.', $name);
                    $blocked = true;

                    continue;
                }

                $entry[] = $name.': '.$expression;
            }

            $entries[] = '{'.implode(', ', $entry).'}';
        }

        return new ImageMigrationFinding(
            $line,
            $offset,
            $element,
            $blocked || null === $props ? null : '<twig:Picture '.implode(' ', $props).([] === $entries ? '' : ' :sources="['.implode(', ', $entries).']"').' />',
            $notes,
        );
    }

    /**
     * The props of the recipe for the attributes of an `img`, none when it cannot be proposed, and the notes.
     *
     * @return array{list<string>|null, list<string>}
     */
    private function image(string $source): array
    {
        $attributes = $this->attributes($source);

        if (null === $attributes) {
            return [null, ['The tag holds a Twig block or prints its attributes: rewrite it by hand.']];
        }

        $notes = [];
        $blocked = false;

        foreach (['src' => 'The image has no src.', 'alt' => 'The image has no text alternative: write its alt, or alt="" for a decorative image.'] as $name => $note) {
            if (!\array_key_exists($name, $attributes)) {
                $notes[] = $note;
                $blocked = true;
            }
        }

        $src = $attributes['src'] ?? '';

        if (1 === preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $src) && !str_starts_with(strtolower($src), 'data:')) {
            $notes[] = 'The image is on another host: list its host under phil_ux_kit.image.hosts, or the recipe refuses it.';
        }

        $props = [];
        $kept = [];

        foreach ($attributes as $name => $value) {
            if (1 === preg_match(self::KEPT, $name)) {
                $kept[] = $this->attribute($name, $value);

                continue;
            }

            [$prop, $note, $blocks] = $this->prop($name, $value, \array_key_exists('fetchpriority', $attributes));

            if (null !== $prop) {
                $props[] = $prop;
            }

            if (null !== $note) {
                $notes[] = $note;
            }

            $blocked = $blocked || $blocks;
        }

        foreach (['width', 'height'] as $name) {
            if (!\array_key_exists($name, $attributes)) {
                $notes[] = \sprintf('The image has no %s: give its size, so that its space is kept while it loads.', $name);
                $blocked = true;
            }
        }

        return [$blocked ? null : [...$props, ...$kept], $notes];
    }

    /**
     * The prop of the recipe for an attribute of an `img`, none when it is a default of the recipe or has no place in
     * it; the note it calls for; and whether it keeps the image from being proposed.
     *
     * @return array{string|null, string|null, bool}
     */
    private function prop(string $name, string $value, bool $prioritised): array
    {
        return match (true) {
            \in_array($name, ['src', 'alt', 'srcset', 'sizes'], true) => [$this->attribute($name, $value), null, false],
            \in_array($name, ['width', 'height'], true) => $this->size($name, $value),
            'loading' === $name => ['lazy' === $value || $prioritised ? null : $this->attribute($name, $value), null, false],
            'decoding' === $name => ['async' === $value ? null : $this->attribute($name, $value), null, false],
            'fetchpriority' === $name && 'high' === $value => [':priority="true"', 'The image becomes the main image of the page: preloaded, fetched first and loaded eagerly.', false],
            'fetchpriority' === $name && 'low' === $value => [':deferred="true"', null, false],
            default => [null, \sprintf('The attribute %s has no place in the recipe.', $name), true],
        };
    }

    /**
     * The prop of a width or a height, the note of a unit dropped, and whether it is no size at all.
     *
     * @return array{string|null, string|null, bool}
     */
    private function size(string $name, string $value): array
    {
        $number = $this->number($value);

        if (null === $number) {
            return [null, \sprintf('The %s "%s" is no number of pixels: give the size of the image.', $name, $value), true];
        }

        return [':'.$name.'="'.$number.'"', str_ends_with($value, 'px') ? \sprintf('The %s loses its unit: a size of an image is in pixels.', $name) : null, false];
    }

    /**
     * The attributes of a tag, by lowercase name, a value-less one as an empty string; none when the tag holds
     * anything else, such as a Twig block or attributes printed whole.
     *
     * @return array<string, string>|null
     */
    private function attributes(string $source): ?array
    {
        $source = rtrim($source, "/ \t\r\n");
        $attributes = [];
        $position = 0;

        while ($position < \strlen($source)) {
            if (1 !== preg_match('~\G\s*([a-zA-Z_:][a-zA-Z0-9_:.-]*)(?:\s*=\s*('.self::QUOTED.'|[^\s"\'=<>`{]+))?\s*~s', $source, $match, 0, $position)) {
                return null;
            }

            $value = $match[2] ?? '';
            $attributes[strtolower($match[1])] = '' !== $value && \in_array($value[0], ['"', "'"], true) ? substr($value, 1, -1) : $value;
            $position += \strlen($match[0]);
        }

        return $attributes;
    }

    /**
     * An attribute of the recipe, its value as it was, between double quotes unless it holds one and no single quote.
     */
    private function attribute(string $name, string $value): string
    {
        return str_contains($value, '"') && !str_contains($value, "'") ? $name."='".$value."'" : $name.'="'.$value.'"';
    }

    /**
     * A size in pixels as a number, or the expression that prints it; null for anything else.
     */
    private function number(string $value): ?string
    {
        if (1 === preg_match('/^\s*(\d+)(?:px)?\s*$/', $value, $match)) {
            return $match[1];
        }

        return !str_contains($value, '"') && 1 === preg_match('/^\s*\{\{(?!.*\{\{)(.+?)\}\}\s*$/s', $value, $match) ? trim($match[1]) : null;
    }

    /**
     * A value as a Twig expression: a string, what it prints, or both joined; null when it holds a block, or a double
     * quote, which would close the attribute of the proposal.
     */
    private function expression(string $value): ?string
    {
        if (1 === preg_match('/\{[%#]|"/', $value)) {
            return null;
        }

        $pieces = preg_split('/(\{\{.*?\}\})/s', $value, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        \assert(false !== $pieces);
        $parts = [];

        foreach ($pieces as $part) {
            $parts[] = str_starts_with($part, '{{') ? '('.trim(substr($part, 2, -2)).')' : "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $part)."'";
        }

        if ([] === $parts) {
            return "''";
        }

        return 1 === \count($parts) && str_starts_with($parts[0], '(') ? substr($parts[0], 1, -1) : implode(' ~ ', $parts);
    }

    /**
     * A match blanked to spaces of the same length, which keeps the offsets of the rest.
     *
     * @param array<array-key, string> $match
     */
    private function blank(array $match): string
    {
        return str_repeat(' ', \strlen($match[0]));
    }

    private function line(string $template, int $offset): int
    {
        return substr_count($template, "\n", 0, $offset) + 1;
    }
}
