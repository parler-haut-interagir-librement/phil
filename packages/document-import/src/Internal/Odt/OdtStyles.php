<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Odt;

use Dom\Element;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Internal\Xml;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;

/**
 * The styles of an OpenDocument text, common and automatic, as far as the import reads them: the character
 * formatting of a style and of its parents, the role of a paragraph style, its alignment and page break, the direct
 * formatting the import leaves out, and the levels of list styles.
 *
 * @internal
 */
final readonly class OdtStyles
{
    public const string OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    public const string STYLE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';

    public const string TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    private const string FO = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

    private const array COUNTERS = ['i' => 'lower-roman', 'I' => 'upper-roman', 'a' => 'lower-alpha', 'A' => 'upper-alpha'];

    private const array ALIGNMENTS = ['center' => 'center', 'end' => 'right', 'right' => 'right', 'justify' => 'justify'];

    /** @var array<string, array<string, Element>> */
    private array $styles;

    /** @var array<string, array<string, Element>> */
    private array $automatic;

    /** @var array<string, Element> */
    private array $lists;

    public function __construct(private StyleMap $map = new StyleMap(), ?Element ...$roots)
    {
        $styles = [];
        $automatic = [];
        $lists = [];

        foreach ($roots as $root) {
            foreach (Xml::children($root, self::OFFICE) as $group) {
                foreach (Xml::children($group, self::STYLE, 'style') as $style) {
                    $family = $style->getAttributeNS(self::STYLE, 'family') ?? '';
                    $name = $style->getAttributeNS(self::STYLE, 'name') ?? '';
                    $styles[$family][$name] = $style;

                    if ('automatic-styles' === $group->localName) {
                        $automatic[$family][$name] = $style;
                    }
                }

                foreach (Xml::children($group, self::TEXT, 'list-style') as $list) {
                    $lists[$list->getAttributeNS(self::STYLE, 'name') ?? ''] = $list;
                }
            }
        }

        $this->styles = $styles;
        $this->automatic = $automatic;
        $this->lists = $lists;
    }

    /**
     * The block the application's map makes of a paragraph style or of the first of its parents the map names, by
     * its display name or its name; `null` when the map names none.
     */
    public function mapped(string $name): ?StyleRole
    {
        foreach ($this->chain('paragraph', $name) as $key => $style) {
            $role = $this->map->role($this->displayName($style)) ?? $this->map->role((string) $key);

            if ($role instanceof StyleRole) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The name a paragraph style shows.
     */
    public function name(string $name): string
    {
        $style = $this->styles['paragraph'][$name] ?? null;

        return null === $style ? $name : $this->displayName($style);
    }

    /**
     * Whether a paragraph style makes a title, a quotation, code or a plain paragraph.
     */
    public function role(string $name): Role
    {
        foreach ($this->chain('paragraph', $name) as $style) {
            $role = match (strtolower($this->displayName($style))) {
                'title' => Role::Heading1,
                'quotations', 'quote' => Role::Quote,
                'preformatted text', 'source text' => Role::Code,
                default => null,
            };

            if (null !== $role) {
                return $role;
            }
        }

        return Role::Paragraph;
    }

    /**
     * The character formatting of a style and of its parents.
     *
     * @return list<Mark>
     */
    public function marks(string $family, string $name): array
    {
        $marks = [];

        foreach ($this->chain($family, $name) as $style) {
            $code = \in_array(strtolower($this->displayName($style)), ['source text', 'teletype', 'example'], true);
            $marks = [...$marks, ...$this->textMarks(Xml::child($style, self::STYLE, 'text-properties')), ...($code ? [Mark::Code] : [])];
        }

        return $marks;
    }

    /**
     * The character formatting of an automatic style alone: the direct formatting of a paragraph, which its common
     * style — a heading's bold — does not add to.
     *
     * @return list<Mark>
     */
    public function directMarks(string $family, string $name): array
    {
        return $this->textMarks(Xml::child($this->automatic[$family][$name] ?? null, self::STYLE, 'text-properties'));
    }

    /**
     * The direct formatting of an automatic style the import leaves out: `colour`, `font`, `size`.
     *
     * @return list<string>
     */
    public function leftOut(string $family, string $name): array
    {
        $properties = Xml::child($this->automatic[$family][$name] ?? null, self::STYLE, 'text-properties');
        $details = [];

        foreach ([[self::FO, 'color', 'colour'], [self::STYLE, 'font-name', 'font'], [self::FO, 'font-size', 'size']] as [$namespace, $attribute, $detail]) {
            if (null !== $properties?->getAttributeNS($namespace, $attribute)) {
                $details[] = $detail;
            }
        }

        return $details;
    }

    public function isHidden(string $family, string $name): bool
    {
        return array_any($this->chain($family, $name), static fn (Element $style): bool => 'none' === Xml::child($style, self::STYLE, 'text-properties')?->getAttributeNS(self::TEXT, 'display'));
    }

    public function alignment(string $name): ?string
    {
        return self::ALIGNMENTS[$this->paragraphProperty($name, 'text-align') ?? ''] ?? null;
    }

    public function breaksPage(string $name): bool
    {
        return 'page' === $this->paragraphProperty($name, 'break-before');
    }

    /**
     * The kind of a list style from its outer level: ordered or not, its counter when it is not decimal, its first
     * number when it is not 1.
     *
     * @return array{bool, ?string, ?int}
     */
    public function list(string $name): array
    {
        foreach (Xml::children($this->lists[$name] ?? null, self::TEXT, 'list-level-style-number') as $level) {
            if ('1' === $level->getAttributeNS(self::TEXT, 'level')) {
                $start = (int) ($level->getAttributeNS(self::TEXT, 'start-value') ?? '1');

                return [true, self::COUNTERS[$level->getAttributeNS(self::STYLE, 'num-format') ?? ''] ?? null, 1 === $start ? null : $start];
            }
        }

        return [false, null, null];
    }

    /**
     * @return list<Mark>
     */
    private function textMarks(?Element $properties): array
    {
        $weight = $properties?->getAttributeNS(self::FO, 'font-weight') ?? 'normal';
        $position = $properties?->getAttributeNS(self::STYLE, 'text-position') ?? '0';
        $marks = [];

        foreach ([
            [Mark::Bold, 'bold' === $weight || (int) $weight >= 600],
            [Mark::Italic, \in_array($properties?->getAttributeNS(self::FO, 'font-style'), ['italic', 'oblique'], true)],
            [Mark::Underline, !\in_array($properties?->getAttributeNS(self::STYLE, 'text-underline-style'), [null, 'none'], true)],
            [Mark::Strikethrough, !\in_array($properties?->getAttributeNS(self::STYLE, 'text-line-through-style'), [null, 'none'], true)],
            [Mark::Marker, !\in_array($properties?->getAttributeNS(self::FO, 'background-color'), [null, 'transparent'], true)],
            [Mark::Superscript, str_starts_with($position, 'super') || (float) $position > 0],
            [Mark::Subscript, str_starts_with($position, 'sub') || str_starts_with($position, '-')],
        ] as [$mark, $on]) {
            if ($on) {
                $marks[] = $mark;
            }
        }

        return $marks;
    }

    /**
     * The name a style shows: its display name, or its name with the encoded spaces decoded.
     */
    private function displayName(Element $style): string
    {
        return $style->getAttributeNS(self::STYLE, 'display-name') ?? str_replace('_20_', ' ', $style->getAttributeNS(self::STYLE, 'name') ?? '');
    }

    private function paragraphProperty(string $name, string $property): ?string
    {
        foreach ($this->chain('paragraph', $name) as $style) {
            $value = Xml::child($style, self::STYLE, 'paragraph-properties')?->getAttributeNS(self::FO, $property);

            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * A style followed by its parents, each once, by name — a numeric one an integer key.
     *
     * @return array<array-key, Element>
     */
    private function chain(string $family, string $name): array
    {
        $chain = [];

        while (null !== ($style = $this->styles[$family][$name] ?? null) && !isset($chain[$name])) {
            $chain[$name] = $style;
            $name = $style->getAttributeNS(self::STYLE, 'parent-style-name') ?? '';
        }

        return $chain;
    }
}
