<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

use Phil\Slides\Domain\ValueObject\DeckSettings;
use Phil\Ujo\Html\LanguageTag;

/**
 * The global directives of a deck, read from the metadata of its source — the YAML front matter `phil/markup` reads
 * before a Markdown text —, with the names Marp gives them: `title`, `lang`, `class`, `paginate`, `header`, `footer`,
 * `size` and `headingDivider`. A value of the wrong kind is refused and its name noted; `theme`, `style` and the
 * colours and images of the background, which the look of the site decides, are noted as not kept.
 */
final readonly class Directives
{
    /** The longest title, header or footer, in characters. */
    public const int MAX_TEXT = 200;

    /** The most classes the directive `class` gives each slide. */
    public const int MAX_CLASSES = 16;

    private const string CLASS_NAME = '/^[A-Za-z0-9_-]{1,64}$/D';

    /**
     * @param list<string> $ignored
     */
    private function __construct(
        public DeckSettings $settings,
        public ?int $headingDivider,
        public array $ignored,
    ) {
    }

    /**
     * @param array<array-key, mixed> $metadata
     */
    public static function read(array $metadata, AspectRatio $aspectRatio = AspectRatio::Wide): self
    {
        $paginate = $metadata['paginate'] ?? null;
        $kept = [
            'title' => self::text($metadata['title'] ?? null),
            'lang' => self::language($metadata['lang'] ?? null),
            'class' => self::classes($metadata['class'] ?? null),
            'paginate' => \is_bool($paginate) ? $paginate : null,
            'header' => self::text($metadata['header'] ?? null),
            'footer' => self::text($metadata['footer'] ?? null),
            'size' => self::size($metadata['size'] ?? null),
            'headingDivider' => self::divider($metadata['headingDivider'] ?? null),
        ];
        $ignored = [];

        foreach (array_keys($metadata) as $name) {
            if (null === ($kept[(string) $name] ?? null)) {
                $ignored[] = (string) $name;
            }
        }

        $settings = new DeckSettings($kept['title'], $kept['lang'], $kept['class'] ?? [], $kept['paginate'] ?? false, $kept['header'], $kept['footer'], $kept['size'] ?? $aspectRatio);

        return new self($settings, $kept['headingDivider'], $ignored);
    }

    private static function language(mixed $value): ?string
    {
        return \is_string($value) && LanguageTag::isValid($value) ? $value : null;
    }

    private static function size(mixed $value): ?AspectRatio
    {
        return \is_string($value) ? AspectRatio::tryFrom($value) : null;
    }

    private static function divider(mixed $value): ?int
    {
        return \is_int($value) && $value >= 1 && $value <= 6 ? $value : null;
    }

    private static function text(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        return '' === $text || mb_strlen($text) > self::MAX_TEXT ? null : $text;
    }

    /**
     * @return list<string>|null
     */
    private static function classes(mixed $value): ?array
    {
        if (!\is_string($value)) {
            return null;
        }

        $classes = array_filter(explode(' ', strtr($value, "\t\n\r\f\v", '     ')), static fn (string $class): bool => '' !== $class);

        if ([] === $classes || \count($classes) > self::MAX_CLASSES) {
            return null;
        }

        foreach ($classes as $class) {
            if (1 !== preg_match(self::CLASS_NAME, $class)) {
                return null;
            }
        }

        return array_values(array_unique($classes));
    }
}
