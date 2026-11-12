<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * A second translated record, whose translation class keeps its index as the entity declares it. It names a field its
 * translation does not map, and a test can make it return any translation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gadget')]
class Gadget implements Translatable
{
    public ?Translation $next = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }

    #[Override]
    public static function translationClass(): string
    {
        return GadgetTranslation::class;
    }

    #[Override]
    public static function translatedFields(): array
    {
        return ['name', 'colour'];
    }

    #[Override]
    public function newTranslation(Locale $locale): Translation
    {
        return $this->next ?? new GadgetTranslation($this, $locale);
    }
}
