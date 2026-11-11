<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Twig;

use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Application\Presentation\Icon;
use Phil\EnumsBundle\Application\Selection\CaseSelection;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Infrastructure\Translation\TranslatableText;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use UnitEnum;

/**
 * Enums in a template:
 *
 * ```twig
 * {% for status in enum_cases('App\\OrderStatus', {group: 'customer'}) %}
 *     <option value="{{ status.value }}">{{ status|enum_label }}</option>
 * {% endfor %}
 * {{ status|enum_meta('App\\Color') }} {{ status|enum_description }}
 * {{ status|enum_badge }}
 * ```
 *
 * `enum_label` and `enum_description` translate when the enum is translatable and a translator is there, and give
 * the case's `Label` — or its name — and `Description` otherwise. `enum_badge` renders `@PhilEnums/badge.html.twig`
 * with the label and the case's `Color` and `Icon`.
 */
final readonly class EnumsExtension
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * The cases of an enum, narrowed by `only`, `except`, `group` and `where_meta`, as the form option of the same
     * names.
     *
     * @param class-string<UnitEnum> $enum
     * @param array{only?: list<UnitEnum>, except?: list<UnitEnum>, group?: string|list<string>, where_meta?: array<class-string<AbstractMetaProperty<*>>, mixed>} $options
     *
     * @return CaseCollection<UnitEnum>
     */
    #[AsTwigFunction(name: 'enum_cases')]
    public function cases(string $enum, array $options = []): CaseCollection
    {
        return CaseSelection::fromOptions($enum, $options['only'] ?? [], $options['except'] ?? [], $options['group'] ?? [], $options['where_meta'] ?? [])->cases();
    }

    /**
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     */
    #[AsTwigFilter(name: 'enum_meta')]
    public function meta(UnitEnum $case, string $metaProperty): mixed
    {
        return Reflection::metaValue($metaProperty, $case);
    }

    #[AsTwigFilter(name: 'enum_label')]
    public function label(UnitEnum $case): string
    {
        if ($case instanceof TranslatableInterface && $this->translator instanceof TranslatorInterface) {
            return $case->trans($this->translator);
        }

        return EnumTranslation::plainLabel($case);
    }

    #[AsTwigFilter(name: 'enum_description')]
    public function description(UnitEnum $case): ?string
    {
        $key = EnumTranslation::descriptionKey($case);

        if (null === $key || !$case instanceof TranslatableInterface || !$this->translator instanceof TranslatorInterface) {
            return $key;
        }

        return new TranslatableText($key, EnumTranslation::domain($case))->trans($this->translator);
    }

    /**
     * The case as a badge: its label, with the `Color` and `Icon` it carries, rendered by
     * `@PhilEnums/badge.html.twig`, which an application overrides to match its design.
     */
    #[AsTwigFilter(name: 'enum_badge', needsEnvironment: true, isSafe: ['html'])]
    public function badge(Environment $environment, UnitEnum $case): string
    {
        return $environment->render('@PhilEnums/badge.html.twig', [
            'case' => $case,
            'label' => $this->label($case),
            'color' => Reflection::metaValue(Color::class, $case),
            'icon' => Reflection::metaValue(Icon::class, $case),
        ]);
    }
}
