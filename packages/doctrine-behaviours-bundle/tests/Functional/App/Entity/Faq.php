<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;

/**
 * A question whose wording is translated.
 */
#[ORM\Entity]
#[ORM\Table(name: 'faq')]
class Faq implements Translatable
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
        return FaqTranslation::class;
    }

    #[Override]
    public static function translatedFields(): array
    {
        return ['question'];
    }

    #[Override]
    public function newTranslation(Locale $locale): FaqTranslation
    {
        return new FaqTranslation($this, $locale);
    }
}
