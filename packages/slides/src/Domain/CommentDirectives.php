<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

/**
 * The directives of one slide written in a `note` tune, as Marp writes them in an HTML comment: `<!-- _class: lead -->`.
 *
 * A note is read as directives only when each of its lines that is not blank is `name: value` with a name Marp gives a
 * directive; any other note stays a note. `class` and `paginate` are kept: with a leading `_` for the slide alone,
 * without it for that slide and the ones after. A value may be quoted. Every other directive name, and a value of the
 * wrong kind, is noted as not kept: the look of the slides is the site's.
 */
final readonly class CommentDirectives
{
    /** The longest note read as directives, in bytes: a longer one is a note. */
    public const int MAX_BYTES = 4_096;

    private const string LINE = '/^[\t ]*(_?)([A-Za-z]{1,32})[\t ]*:[\t ]*(.*?)[\t ]*$/D';

    private const string CLASS_NAME = '/^[A-Za-z0-9_-]{1,64}$/D';

    /** The directives Marp writes for one slide and the ones after it. */
    private const array LOCAL = ['class', 'paginate', 'header', 'footer', 'color', 'backgroundColor', 'backgroundImage', 'backgroundPosition', 'backgroundRepeat', 'backgroundSize'];

    /** The directives Marp writes for the whole deck. */
    private const array GLOBAL = ['theme', 'style', 'headingDivider', 'size', 'math', 'title', 'author', 'description', 'image', 'keywords', 'url', 'lang', 'marp'];

    /**
     * @param list<string>|null $spotClasses    the classes of this slide alone, `null` when the note gives none
     * @param list<string>|null $carriedClasses the classes of this slide and the ones after, `null` when none
     * @param list<string>      $ignored        the names of the directives not kept
     */
    private function __construct(
        public ?array $spotClasses = null,
        public ?array $carriedClasses = null,
        public ?bool $spotPagination = null,
        public ?bool $carriedPagination = null,
        public array $ignored = [],
    ) {
    }

    /**
     * The directives of a note, `null` when the note is not made of directives alone.
     */
    public static function read(string $note): ?self
    {
        if ('' === trim($note) || \strlen($note) > self::MAX_BYTES) {
            return null;
        }

        $directives = [];

        foreach (explode("\n", strtr($note, ["\r\n" => "\n", "\r" => "\n"])) as $line) {
            if ('' === trim($line)) {
                continue;
            }

            if (1 !== preg_match(self::LINE, $line, $match) || !self::isDirective($match[1], $match[2])) {
                return null;
            }

            $directives[] = [$match[1], $match[2], self::unquote($match[3])];
        }

        return self::from($directives);
    }

    /**
     * @param list<array{string, string, string}> $directives
     */
    private static function from(array $directives): self
    {
        $classes = ['_' => null, '' => null];
        $pagination = ['_' => null, '' => null];
        $ignored = [];

        foreach ($directives as [$spot, $name, $value]) {
            $kept = match ($name) {
                'class' => $classes[$spot] = self::classes($value),
                'paginate' => $pagination[$spot] = self::flag($value),
                default => null,
            };

            if (null === $kept) {
                $ignored[] = $spot.$name;
            }
        }

        return new self($classes['_'], $classes[''], $pagination['_'], $pagination[''], array_values(array_unique($ignored)));
    }

    private static function isDirective(string $spot, string $name): bool
    {
        return \in_array($name, self::LOCAL, true) || ('' === $spot && \in_array($name, self::GLOBAL, true));
    }

    private static function unquote(string $value): string
    {
        $length = \strlen($value);

        if ($length >= 2 && ('"' === $value[0] || "'" === $value[0]) && $value[$length - 1] === $value[0]) {
            return trim(substr($value, 1, -1));
        }

        return $value;
    }

    private static function flag(string $value): ?bool
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return list<string>|null
     */
    private static function classes(string $value): ?array
    {
        $classes = array_values(array_unique(array_filter(explode(' ', strtr($value, "\t", ' ')), static fn (string $class): bool => '' !== $class)));

        if ([] === $classes || \count($classes) > Directives::MAX_CLASSES) {
            return null;
        }

        foreach ($classes as $class) {
            if (1 !== preg_match(self::CLASS_NAME, $class)) {
                return null;
            }
        }

        return $classes;
    }
}
