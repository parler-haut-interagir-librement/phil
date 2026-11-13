<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * Something the stockroom keeps: a quantity of interchangeable pieces, or one unit followed on its own. Its code is the
 * short text printed on its label and read back by a scanner; its home is the container it goes back to.
 */
final readonly class Item
{
    public string $name;

    /**
     * @throws InvalidStock when the identifier, the code, the name or the home is not one
     */
    public function __construct(
        public string $id,
        public string $code,
        string $name,
        public Tracking $tracking = Tracking::Quantity,
        public ?string $home = null,
        public ItemCondition $condition = ItemCondition::Usable,
    ) {
        Identifier::check($id, 'identifier of an item');
        Identifier::check($code, 'code of an item');
        $this->name = Text::check($name, 'name of an item');
        Identifier::optional($home, 'home of an item');
    }

    public function withCondition(ItemCondition $condition): self
    {
        return new self($this->id, $this->code, $this->name, $this->tracking, $this->home, $condition);
    }

    /**
     * @throws InvalidStock when the home is not an identifier
     */
    public function withHome(?string $home): self
    {
        return new self($this->id, $this->code, $this->name, $this->tracking, $home, $this->condition);
    }

    public function isUnit(): bool
    {
        return Tracking::Unit === $this->tracking;
    }
}
