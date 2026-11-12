<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\TranslationEntity;
use Phil\LocaleDimensions\Locale;

/**
 * One locale of a gadget, with a generated identifier and its own unique index, the columns in the other order.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gadget_translation')]
#[ORM\UniqueConstraint(name: 'gadget_locale', columns: ['locale', 'gadget_id'])]
class GadgetTranslation implements Translation
{
    use TranslationEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $name = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Gadget::class)]
        #[ORM\JoinColumn(name: 'gadget_id', nullable: false)]
        private Gadget $translatable,
        Locale $locale,
    ) {
        $this->writtenIn($locale);
    }

    #[Override]
    public function translatable(): Gadget
    {
        return $this->translatable;
    }
}
