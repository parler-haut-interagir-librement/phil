<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * The application's table of paragraph styles and the blocks they become, read before the importer's own rules: a
 * style named `Chapter Title` made a level 1 heading, `Rules Box` a quotation, `Author Note` left out. A style is
 * named as the word processor shows it, or by its identifier; case and surrounding spaces do not matter. A style
 * based on a named one becomes what its parent becomes, unless it is named too.
 *
 * DOCX, ODT, flat ODT and RTF documents read the map; Markdown, HTML and PDF have no paragraph styles.
 */
final readonly class StyleMap
{
    /** @var array<string, StyleRole> */
    private array $roles;

    /**
     * @param array<array-key, StyleRole> $roles the block of each style, by its name
     */
    public function __construct(array $roles = [])
    {
        $normalized = [];

        foreach ($roles as $style => $role) {
            $normalized[$this->key((string) $style)] = $role;
        }

        $this->roles = $normalized;
    }

    public function with(string $style, StyleRole $role): self
    {
        return new self(array_replace($this->roles, [$this->key($style) => $role]));
    }

    /**
     * The block a style becomes, `null` when the map does not name it.
     */
    public function role(string $style): ?StyleRole
    {
        return $this->roles[$this->key($style)] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->roles;
    }

    private function key(string $style): string
    {
        return mb_strtolower(trim($style));
    }
}
