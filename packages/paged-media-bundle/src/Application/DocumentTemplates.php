<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Phil\PagedMediaBundle\Application\Exception\UnknownTemplate;

/**
 * The application's document templates, by name.
 */
final readonly class DocumentTemplates
{
    /** @var array<string, DocumentTemplate> */
    private array $templates;

    /**
     * @param iterable<string, DocumentTemplate> $templates
     */
    public function __construct(iterable $templates = [])
    {
        $byName = [];

        foreach ($templates as $name => $template) {
            $byName[$name] = $template;
        }

        $this->templates = $byName;
    }

    public function get(string $name): DocumentTemplate
    {
        return $this->templates[$name] ?? throw UnknownTemplate::named($name, array_keys($this->templates));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->templates);
    }
}
