<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;

/**
 * The resource link a launch opens: the placement of the tool in a context of the platform, stable across launches.
 */
final readonly class ResourceLink
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self($claim->string('id', 255), $claim->optionalString('title'), $claim->optionalString('description'));
    }

    /**
     * @return array<string, string>
     */
    public function toClaim(): array
    {
        return array_filter(['id' => $this->id, 'title' => $this->title, 'description' => $this->description], static fn (?string $value): bool => null !== $value);
    }
}
