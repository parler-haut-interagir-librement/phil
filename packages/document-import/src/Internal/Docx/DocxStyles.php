<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Docx;

use Dom\Element;
use Dom\XMLDocument;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Internal\Xml;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;

/**
 * The styles and the numbering of a WordprocessingML document, as far as the import reads them: which paragraph
 * style is a heading, a quotation or code — the application's map first —, which list a paragraph style belongs to, the character formatting of a
 * character style, and the kind of each level of a list.
 *
 * @internal
 */
final readonly class DocxStyles
{
    public const string W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** The names of the character styles of code. */
    private const array CODE = ['html code', 'verbatim char', 'source code char', 'code char'];

    private const array COUNTERS = [
        'lowerRoman' => 'lower-roman',
        'upperRoman' => 'upper-roman',
        'lowerLetter' => 'lower-alpha',
        'upperLetter' => 'upper-alpha',
    ];

    /** @var array<string, Element> */
    private array $styles;

    /** @var array<string, string> */
    private array $numbers;

    /** @var array<string, int> the first number a numbering instance gives its outer level instead of its definition's */
    private array $starts;

    /** @var array<string, Element> */
    private array $abstracts;

    public function __construct(?XMLDocument $styles, ?XMLDocument $numbering, private StyleMap $map = new StyleMap())
    {
        $this->styles = $this->index($styles?->documentElement, 'style', 'styleId');
        $this->abstracts = $this->index($numbering?->documentElement, 'abstractNum', 'abstractNumId');
        $numbers = [];
        $starts = [];

        foreach ($this->index($numbering?->documentElement, 'num', 'numId') as $id => $num) {
            $numbers[$id] = self::value(Xml::child($num, self::W, 'abstractNumId')) ?? '';

            foreach (Xml::children($num, self::W, 'lvlOverride') as $override) {
                $start = self::value(Xml::child($override, self::W, 'startOverride'));

                if ('0' === $override->getAttributeNS(self::W, 'ilvl') && null !== $start) {
                    $starts[$id] = (int) $start;
                }
            }
        }

        $this->numbers = $numbers;
        $this->starts = $starts;
    }

    /**
     * The block the application's map makes of a paragraph style or of the first style it is based on that the map
     * names, by its name or its identifier; `null` when the map names none.
     */
    public function mapped(string $styleId): ?StyleRole
    {
        foreach ($this->chain($styleId) as $id => $style) {
            $role = $this->map->role(self::value(Xml::child($style, self::W, 'name')) ?? '') ?? $this->map->role((string) $id);

            if ($role instanceof StyleRole) {
                return $role;
            }
        }

        return '' === $styleId || $this->map->isEmpty() ? null : $this->map->role($styleId);
    }

    /**
     * The name a paragraph style shows, its identifier when it has none.
     */
    public function name(string $styleId): string
    {
        return self::value(Xml::child($this->styles[$styleId] ?? null, self::W, 'name')) ?? $styleId;
    }

    /**
     * Whether a paragraph style makes a heading of a level, a quotation, code, or a plain paragraph; `null` when the
     * application's map leaves it out.
     */
    public function role(string $styleId): ?Role
    {
        $mapped = $this->mapped($styleId);

        if ($mapped instanceof StyleRole) {
            return Role::mapped($mapped);
        }

        foreach ($this->chain($styleId) as $style) {
            $name = strtolower(self::value(Xml::child($style, self::W, 'name')) ?? '');
            $outline = self::value(Xml::child(Xml::child($style, self::W, 'pPr'), self::W, 'outlineLvl'));

            $role = match (true) {
                1 === preg_match('/^heading ([1-9])$/D', $name, $match) => Role::heading((int) $match[1]),
                'title' === $name => Role::Heading1,
                \in_array($name, ['quote', 'intense quote', 'block text'], true) => Role::Quote,
                \in_array($name, ['html preformatted', 'source code', 'code'], true) => Role::Code,
                default => null === $outline ? null : Role::outline((int) $outline),
            };

            if ($role instanceof Role) {
                return $role;
            }
        }

        return Role::Paragraph;
    }

    /**
     * The list a paragraph style puts its paragraphs in, as its numbering identifier and level.
     *
     * @return array{string, int}|null
     */
    public function numbering(string $styleId): ?array
    {
        foreach ($this->chain($styleId) as $style) {
            $numbering = self::numberingOf(Xml::child(Xml::child($style, self::W, 'pPr'), self::W, 'numPr'));

            if (null !== $numbering) {
                return $numbering;
            }
        }

        return null;
    }

    /**
     * The numbering identifier and level of a `w:numPr`, `null` when it names none or the identifier 0, which ends a
     * list.
     *
     * @return array{string, int}|null
     */
    public static function numberingOf(?Element $numPr): ?array
    {
        $id = self::value(Xml::child($numPr, self::W, 'numId')) ?? '0';

        return '0' === $id ? null : [$id, (int) self::value(Xml::child($numPr, self::W, 'ilvl'))];
    }

    /**
     * The kind of a list from its outer level: ordered or not, its counter when it is not decimal, its first number
     * when it is not 1. `null` when the numbering is not defined.
     *
     * @return array{bool, ?string, ?int}|null
     */
    public function list(string $numId): ?array
    {
        foreach (Xml::children($this->abstracts[$this->numbers[$numId] ?? ''] ?? null, self::W, 'lvl') as $level) {
            if ('0' === $level->getAttributeNS(self::W, 'ilvl')) {
                $format = self::value(Xml::child($level, self::W, 'numFmt')) ?? 'decimal';
                $start = $this->starts[$numId] ?? (int) (self::value(Xml::child($level, self::W, 'start')) ?? '1');

                return [!\in_array($format, ['bullet', 'none'], true), self::COUNTERS[$format] ?? null, 1 === $start ? null : $start];
            }
        }

        return null;
    }

    /**
     * The character formatting of a character style and of the styles it is based on.
     *
     * @return list<Mark>
     */
    public function marks(string $styleId): array
    {
        $marks = [];

        foreach ($this->chain($styleId) as $style) {
            $name = strtolower(self::value(Xml::child($style, self::W, 'name')) ?? '');

            if ('hyperlink' === $name) {
                continue;
            }

            $marks = [...$marks, ...self::runMarks(Xml::child($style, self::W, 'rPr')), ...(\in_array($name, self::CODE, true) ? [Mark::Code] : [])];
        }

        return $marks;
    }

    /**
     * The character formatting a `w:rPr` sets on: bold, italic, underline, strikethrough, highlight, exponent.
     *
     * @return list<Mark>
     */
    public static function runMarks(?Element $rPr): array
    {
        $marks = [];

        foreach (['b' => Mark::Bold, 'i' => Mark::Italic, 'u' => Mark::Underline, 'strike' => Mark::Strikethrough, 'dstrike' => Mark::Strikethrough, 'highlight' => Mark::Marker] as $name => $mark) {
            if (self::on(Xml::child($rPr, self::W, $name))) {
                $marks[] = $mark;
            }
        }

        $position = self::value(Xml::child($rPr, self::W, 'vertAlign'));

        if ('superscript' === $position) {
            $marks[] = Mark::Superscript;
        }

        if ('subscript' === $position) {
            $marks[] = Mark::Subscript;
        }

        return $marks;
    }

    /**
     * Whether a paragraph style is the heading of a table of contents.
     */
    public function isTocHeading(string $styleId): bool
    {
        return array_any($this->chain($styleId), static fn (Element $style): bool => 'toc heading' === strtolower(self::value(Xml::child($style, self::W, 'name')) ?? ''));
    }

    /**
     * The address of a `HYPERLINK` field to an external target.
     */
    public static function fieldLink(string $instruction): ?string
    {
        return 1 === preg_match('/^\s*HYPERLINK\s+"([^"]+)"/', $instruction, $match) ? $match[1] : null;
    }

    /**
     * Whether a toggle property is on: present without value, or with a value other than off.
     */
    public static function on(?Element $property): bool
    {
        return $property instanceof Element && !\in_array(self::value($property), ['0', 'false', 'off', 'none'], true);
    }

    /**
     * The `w:val` of an element.
     */
    public static function value(?Element $element): ?string
    {
        return $element?->getAttributeNS(self::W, 'val');
    }

    /**
     * A style followed by the styles it is based on, each once, by identifier — a numeric one an integer key.
     *
     * @return array<array-key, Element>
     */
    private function chain(string $styleId): array
    {
        $chain = [];

        while (null !== ($style = $this->styles[$styleId] ?? null) && !isset($chain[$styleId])) {
            $chain[$styleId] = $style;
            $styleId = self::value(Xml::child($style, self::W, 'basedOn')) ?? '';
        }

        return $chain;
    }

    /**
     * @return array<string, Element>
     */
    private function index(?Element $root, string $name, string $key): array
    {
        $index = [];

        foreach (Xml::children($root, self::W, $name) as $element) {
            $index[$element->getAttributeNS(self::W, $key) ?? ''] = $element;
        }

        return $index;
    }
}
