<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Phil\DoctrineBehaviours\SoftDeletableEntity;

/**
 * A soft-deletable parent whose removal cascades: to books with persist and remove, to labels with remove only, and to
 * cards that are soft deletable themselves.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shelf')]
class Shelf implements SoftDeletable
{
    use SoftDeletableEntity;

    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'shelf', cascade: ['persist', 'remove'])]
    public Collection $books;

    /** @var Collection<int, Label> */
    #[ORM\OneToMany(targetEntity: Label::class, mappedBy: 'shelf', cascade: ['remove'])]
    public Collection $labels;

    /** @var Collection<int, Card> */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'shelf', cascade: ['persist', 'remove'])]
    public Collection $cards;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
        $this->books = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->cards = new ArrayCollection();
    }
}
