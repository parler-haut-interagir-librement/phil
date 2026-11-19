<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;

/**
 * The paragraph styles of an RTF style sheet, by number: their names, the style each is based on and its outline
 * level, and the block each makes — the application's map first, then the names a word processor gives its headings,
 * quotations and code, then the outline level.
 *
 * @internal
 */
final class RtfStyles
{
    /** @var array<int, array{name: string, basedOn: ?int, outline: ?int}> */
    private array $styles = [];

    public function __construct(private readonly StyleMap $map)
    {
    }

    public function add(int $number, string $name, ?int $basedOn, ?int $outline): void
    {
        $this->styles[$number] = ['name' => $name, 'basedOn' => $basedOn, 'outline' => $outline];
    }

    public function name(int $number): string
    {
        return $this->styles[$number]['name'] ?? 's'.$number;
    }

    /**
     * Whether the application's map names the style or one it is based on.
     */
    public function isMapped(int $number): bool
    {
        return $this->mapped($number) instanceof StyleRole;
    }

    /**
     * The block a paragraph of a style makes, its own outline level first when it has one; `null` when the
     * application's map leaves the style out.
     */
    public function role(int $number, ?int $outline): ?Role
    {
        $mapped = $this->mapped($number);

        if ($mapped instanceof StyleRole) {
            return Role::mapped($mapped);
        }

        if (null !== $outline) {
            return Role::outline($outline) ?? Role::Paragraph;
        }

        foreach ($this->chain($number) as $style) {
            $name = strtolower($style['name']);

            $role = match (true) {
                1 === preg_match('/^heading ([1-9])$/D', $name, $match) => Role::heading((int) $match[1]),
                'title' === $name => Role::Heading1,
                \in_array($name, ['quote', 'intense quote', 'block text', 'quotations'], true) => Role::Quote,
                \in_array($name, ['html preformatted', 'source code', 'code', 'preformatted text'], true) => Role::Code,
                default => null === $style['outline'] ? null : Role::outline($style['outline']),
            };

            if ($role instanceof Role) {
                return $role;
            }
        }

        return Role::Paragraph;
    }

    private function mapped(int $number): ?StyleRole
    {
        foreach ($this->chain($number) as $style) {
            $role = $this->map->role($style['name']);

            if ($role instanceof StyleRole) {
                return $role;
            }
        }

        return null;
    }

    /**
     * A style followed by the styles it is based on, each once.
     *
     * @return list<array{name: string, basedOn: ?int, outline: ?int}>
     */
    private function chain(int $number): array
    {
        $chain = [];
        $seen = [];

        while (isset($this->styles[$number]) && !isset($seen[$number])) {
            $seen[$number] = true;
            $chain[] = $this->styles[$number];
            $number = $this->styles[$number]['basedOn'] ?? -1;
        }

        return $chain;
    }

    /**
     * The paragraph style a style sheet entry defines; a character, section or table style is not kept.
     */
    public function define(RtfCapture $capture): void
    {
        $properties = $capture->properties;

        if (isset($properties['cs']) || isset($properties['ds']) || isset($properties['ts'])) {
            return;
        }

        $this->add(
            (int) ($properties['s'] ?? 0),
            trim(rtrim(trim($capture->text), ';')),
            isset($properties['sbasedon']) ? (int) $properties['sbasedon'] : null,
            isset($properties['outlinelevel']) ? (int) $properties['outlinelevel'] : null,
        );
    }
}
