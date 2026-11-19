<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * A promotion's label in one locale — *Black Friday −20 %*, *Vendredi fou −20 %* —; what it gives and when are the
 * promotion's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_promotion_translation')]
#[ORM\UniqueConstraint(name: 'phil_commerce_promotion_translation_translatable_locale', columns: ['promotion', 'locale'])]
final class PromotionTranslation implements Translation
{
    /** The locale, in the one spelling of `phil/locale-dimensions`. */
    #[ORM\Column(name: 'locale', length: Translation::LOCALE_LENGTH)]
    private readonly string $locale;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'label', length: 255)]
    private string $label = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Promotion::class)]
        #[ORM\JoinColumn(name: 'promotion', referencedColumnName: 'code', nullable: false, onDelete: 'CASCADE')]
        private readonly Promotion $translatable,
        Locale $locale,
    ) {
        $this->locale = $locale->value;
    }

    #[Override]
    public function locale(): Locale
    {
        return Locale::fromString($this->locale);
    }

    #[Override]
    public function translatable(): Promotion
    {
        return $this->translatable;
    }

    /**
     * The label in this locale; empty until it is written.
     */
    public function label(): string
    {
        return $this->label;
    }

    public function relabel(string $label): void
    {
        if ('' === trim($label) || mb_strlen($label) > 255) {
            throw InvalidValue::because(\sprintf('The promotion %s has a label of 1 to 255 characters in %s.', $this->translatable->code, $this->locale));
        }

        $this->label = $label;
    }
}
