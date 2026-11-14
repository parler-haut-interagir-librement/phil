<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * How many entries of a form keep a choice that has a capacity: one row per choice, counted up and down by the store
 * in single statements, so that two entries never take the last place together. Mapped for the schema; read and
 * written through `ChoicePlaces`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_form_choice_count')]
final class ChoiceCount
{
    private function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Form::class)]
        #[ORM\JoinColumn(name: 'form', nullable: false, onDelete: 'CASCADE')]
        public readonly Form $form,
        #[ORM\Id]
        #[ORM\Column(name: 'field_name', length: 64)]
        public readonly string $field,
        #[ORM\Id]
        #[ORM\Column(name: 'choice', length: 255)]
        public readonly string $choice,
        #[ORM\Column(name: 'taken')]
        public readonly int $taken,
    ) {
    }
}
