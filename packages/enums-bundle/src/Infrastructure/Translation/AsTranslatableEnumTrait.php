<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Translation;

use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Makes the cases of an enum translatable, on Symfony's `TranslatableInterface`, which the enum declares:
 *
 * ```php
 * #[Meta(Label::class, Description::class)]
 * enum OrderStatus: string implements TranslatableInterface
 * {
 *     use AsMetadatableEnumTrait;
 *     use AsTranslatableEnumTrait;
 *
 *     #[Label('In transit')] #[Description('The parcel has left the warehouse.')]
 *     case IN_TRANSIT = 'in_transit';
 * }
 * ```
 *
 * `{{ status|trans }}` in Twig, a translated message or the label of a form choice then shows the translation of
 * the case's label; `translatableDescription()` gives its description the same way.
 *
 * @phpstan-require-implements TranslatableInterface
 */
trait AsTranslatableEnumTrait
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(EnumTranslation::labelKey($this), [], EnumTranslation::domain($this), $locale);
    }

    /**
     * The case's description, to translate when rendered; `null` when the case has none.
     */
    public function translatableDescription(): ?TranslatableInterface
    {
        $key = EnumTranslation::descriptionKey($this);

        return null === $key ? null : new TranslatableText($key, EnumTranslation::domain($this));
    }
}
