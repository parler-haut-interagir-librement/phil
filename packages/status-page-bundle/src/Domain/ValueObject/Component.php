<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

/**
 * A part of the service visitors know by name — the website, the API, sign-in, payments —, with an optional
 * description and the group it is shown under.
 */
final readonly class Component
{
    /** @var non-empty-string */
    public string $id;

    /** @var non-empty-string */
    public string $name;

    public ?string $description;

    public ?string $group;

    public function __construct(string $id, string $name, ?string $description = null, ?string $group = null)
    {
        $this->id = Text::id($id, 'component id');
        $this->name = Text::line($name, 'component name', 120);
        $this->description = null === $description ? null : Text::line($description, 'component description', 300);
        $this->group = null === $group ? null : Text::line($group, 'component group', 120);
    }
}
