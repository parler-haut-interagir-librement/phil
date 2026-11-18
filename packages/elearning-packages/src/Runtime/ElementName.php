<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * The name of an element read by the data model: its pattern, each index of a collection replaced by `n` — a name
 * that holds the segment `n` itself is none —, the indexes,
 * and the keyword that ends it — `_children`, `_count` or `_version` —, if any. The target of a navigation request
 * of SCORM 2004, `adl.nav.request_valid.choice.{target=intro}`, is kept apart, so that the dots it holds are not read
 * as segments.
 */
final readonly class ElementName
{
    public const array KEYWORDS = ['_children', '_count', '_version'];

    /**
     * @param list<int> $indexes
     */
    private function __construct(
        public string $name,
        public string $pattern,
        public array $indexes,
        public ?string $keyword,
        public ?string $target,
    ) {
    }

    /**
     * The name read, or null when it is not one an element of a data model may have: empty, too long, with an empty
     * segment, or with an index of more than four digits or a leading zero.
     */
    public static function parse(string $name): ?self
    {
        if ('' === $name || \strlen($name) > DataModel::MAX_ELEMENT_LENGTH) {
            return null;
        }

        if (str_starts_with($name, 'adl.nav.request_valid.') && 1 === preg_match('/^(adl\.nav\.request_valid\.(?:choice|jump))\.(\{target=[^\s{}]{1,200}\})$/D', $name, $match)) {
            return new self($name, $match[1], [], null, $match[2]);
        }

        if (1 !== preg_match('/^[a-z0-9_.]+$/D', $name)) {
            return null;
        }

        $segments = explode('.', $name);
        $pattern = [];
        $indexes = [];
        $keyword = null;
        $last = \count($segments) - 1;

        foreach ($segments as $position => $segment) {
            $length = \strlen($segment);

            if (0 === $length || $length > 41) {
                return null;
            }

            if (\strlen($segment) === strspn($segment, '0123456789')) {
                if ($length > 4 || '0' === $segment[0] && 1 !== $length) {
                    return null;
                }

                $pattern[] = 'n';
                $indexes[] = (int) $segment;

                continue;
            }

            // `n` stands for an index in the patterns of the data model: a name never holds it.
            if (1 === strspn($segment, '0123456789', 0, 1) || 'n' === $segment) {
                return null;
            }

            if ('_' === $segment[0] && \in_array($segment, self::KEYWORDS, true)) {
                if ($position !== $last) {
                    return null;
                }

                $keyword = $segment;

                continue;
            }

            $pattern[] = $segment;
        }

        return new self($name, implode('.', $pattern), $indexes, $keyword, null);
    }

    /**
     * The name of the collection that holds the element at this depth of indexes, with the indexes above it filled:
     * `cmi.interactions.3.objectives` for the second index of `cmi.interactions.3.objectives.0.id`.
     */
    public function collection(int $depth): string
    {
        $segments = explode('.', $this->pattern);
        $filled = [];
        $seen = 0;

        foreach ($segments as $segment) {
            if ('n' === $segment) {
                if ($seen === $depth) {
                    break;
                }

                $filled[] = (string) $this->indexes[$seen];
                ++$seen;

                continue;
            }

            $filled[] = $segment;
        }

        return implode('.', $filled);
    }

    /**
     * The pattern of the collection at this depth: `cmi.interactions.n.objectives` for the second index.
     */
    public function collectionPattern(int $depth): string
    {
        $segments = explode('.', $this->pattern);
        $kept = [];
        $seen = 0;

        foreach ($segments as $segment) {
            if ('n' === $segment) {
                if ($seen === $depth) {
                    break;
                }

                ++$seen;
            }

            $kept[] = $segment;
        }

        return implode('.', $kept);
    }

    /**
     * The name of the element of the same index whose last segments are these: `cmi.interactions.3.id` for `id` from
     * `cmi.interactions.3.type`, at the depth of the first index.
     */
    public function sibling(int $depth, string $suffix): string
    {
        return $this->collection($depth).'.'.$this->indexes[$depth].'.'.$suffix;
    }
}
