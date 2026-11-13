<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

/**
 * A shortcut of the installed site, offered by the operating system on its icon: a name, the path it opens, and an
 * optional short name and description.
 */
final readonly class Shortcut
{
    /** @var non-empty-string */
    public string $name;

    /** @var non-empty-string */
    public string $url;

    public ?string $shortName;

    public ?string $description;

    public function __construct(string $name, string $url, ?string $shortName = null, ?string $description = null)
    {
        $this->name = Rule::line($name, 'name of a shortcut', 60);
        $this->url = Rule::localPath($url, 'address of a shortcut');
        $this->shortName = null === $shortName ? null : Rule::line($shortName, 'short name of a shortcut', 30);
        $this->description = null === $description ? null : Rule::line($description, 'description of a shortcut', 300);
    }
}
