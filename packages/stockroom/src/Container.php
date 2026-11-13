<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * A place in the stockroom that holds items and other containers: a room, a shelf, a crate, a bag. Its parent is the
 * container it sits in, none at the top; its code is the short text printed on its label.
 */
final readonly class Container
{
    public string $name;

    /**
     * @throws InvalidStock when the identifier, the name, the parent or the code is not one
     */
    public function __construct(
        public string $id,
        string $name,
        public ?string $parent = null,
        public ?string $code = null,
    ) {
        Identifier::check($id, 'identifier of a container');
        $this->name = Text::check($name, 'name of a container');
        Identifier::optional($parent, 'parent of a container');
        Identifier::optional($code, 'code of a container');

        if ($parent === $id) {
            throw InvalidStock::because('A container does not sit in itself.');
        }
    }

    /**
     * The same container in another parent, or at the top.
     *
     * @throws InvalidStock when the parent is not an identifier or is the container itself
     */
    public function in(?string $parent): self
    {
        return new self($this->id, $this->name, $parent, $this->code);
    }

    public function place(): Place
    {
        return Place::container($this->id);
    }
}
