<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * One element the sanitizer keeps: the tags it is written with, the tag and class it is written back as, and the
 * attributes it may carry.
 *
 * A pattern of `accepts` is a tag, `span`; a tag and a class, `span.inline-code`; or a tag and an attribute,
 * `a[data-mention]`. A profile tries its tools in order, so a tool with a narrower pattern comes before a wider one.
 * A tool `within` tags is kept only inside an element written as one of them — the reading of a ruby inside its ruby,
 * the elements of MathML inside a formula —; a tool with attributes in `anyOf` only when it keeps one of them.
 */
final readonly class InlineTool
{
    /** @var list<array{string, string, string}> each pattern read once: its tag, its class and its attribute, '' when absent */
    private array $patterns;

    /**
     * @param list<string>                  $accepts    the patterns of the elements it reads
     * @param array<string, AttributeValue> $attributes the attributes it keeps, in the order it writes them
     * @param list<string>                  $required   the attributes without which the element is not this tool
     * @param list<string>                  $within     the tags, as written, one of which must hold the element
     * @param list<string>                  $anyOf      the attributes of which the element must keep at least one
     */
    public function __construct(
        public string $name,
        public string $tag,
        public array $accepts,
        public ?string $class = null,
        public array $attributes = [],
        public array $required = [],
        public array $within = [],
        public array $anyOf = [],
    ) {
        $patterns = [];

        foreach ($accepts as $pattern) {
            if (1 === preg_match('/^([a-z][a-z0-9]*)(?:\.([A-Za-z0-9_-]+)|\[([a-z-]+)])?$/D', $pattern, $match)) {
                $patterns[] = [$match[1], $match[2] ?? '', $match[3] ?? ''];
            }
        }

        $this->patterns = $patterns;
    }

    /**
     * The tags of the elements it reads, each once; a pattern that is not one of the three forms reads none.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values(array_unique(array_column($this->patterns, 0)));
    }

    /**
     * Whether an element of a tag, with its attributes, is written with this tool.
     *
     * @param array<string, string> $attributes
     */
    public function accepts(string $tag, array $attributes): bool
    {
        foreach ($this->patterns as [$read, $class, $attribute]) {
            if ($read === $tag
                && ('' === $class || \in_array($class, explode(' ', $attributes['class'] ?? ''), true))
                && ('' === $attribute || isset($attributes[$attribute]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the tool may be written where the elements open around it are written as `$inside`.
     *
     * @param array<string, int> $inside how many elements of each written tag are open
     */
    public function fits(array $inside): bool
    {
        return [] === $this->within || array_any($this->within, static fn (string $tag): bool => 0 < ($inside[$tag] ?? 0));
    }

    /**
     * The attributes the tool keeps, each cleaned, in its own order, its class first; `null` when a required one is
     * missing or refused, or when none of `anyOf` is kept.
     *
     * @param array<string, string> $attributes
     *
     * @return ?array<string, string>
     */
    public function clean(array $attributes): ?array
    {
        $clean = null === $this->class ? [] : ['class' => $this->class];

        foreach ($this->attributes as $name => $value) {
            $kept = isset($attributes[$name]) ? $value->clean($attributes[$name]) : null;

            if (null !== $kept) {
                $clean[$name] = $kept;
            } elseif (\in_array($name, $this->required, true)) {
                return null;
            }
        }

        return [] === $this->anyOf || [] !== array_intersect_key($clean, array_flip($this->anyOf)) ? $clean : null;
    }
}
