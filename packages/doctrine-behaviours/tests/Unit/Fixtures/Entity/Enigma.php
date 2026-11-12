<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\LooseTranslation;
use Phil\LocaleDimensions\Locale;

/**
 * A record that names, as its translation, an entity that is not one.
 */
#[ORM\Entity]
#[ORM\Table(name: 'enigma')]
class Enigma implements Translatable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }

    #[Override]
    public static function translationClass(): string
    {
        /** @phpstan-ignore return.type (the mistake this fixture makes) */
        return Track::class;
    }

    #[Override]
    public static function translatedFields(): array
    {
        return ['text'];
    }

    #[Override]
    public function newTranslation(Locale $locale): Translation
    {
        return new LooseTranslation($this, $locale);
    }
}
