<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Infrastructure\DependencyInjection;

use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Preset;
use Phil\Typography\Typographer;
use Phil\Typography\Typographers;
use Phil\TypographyBundle\Infrastructure\Twig\TypographyExtension;
use Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter;
use Phil\Ujo\Html\HtmlFilter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * The typographers of the configuration, each a service: `Typographers`, which builds one per locale; `Typographer`,
 * the one of the default locale; one service per configured locale, `phil_typography.typographer.<locale>`, also
 * injected into an argument named after it (`Typographer $frCATypographer`); the Twig filters; and the filter of UJO
 * HTML. The filters follow Symfony's `LocaleSwitcher` when the translator registers it.
 */
final class TypographyServices
{
    public const array PROTECTED_TAGS = Typographer::PROTECTED_TAGS;

    public const array FIXERS = Preset::FIXERS;

    public const array EXPERIMENTAL = Dictionary::EXPERIMENTAL;

    public const string TYPOGRAPHER = 'phil_typography.typographer.';

    public const string UJO_FILTER = 'phil_typography.ujo_filter';

    public const string UJO_RENDERER = 'phil_typography.ujo_renderer';

    /** The `LocaleSwitcher` of Symfony's translator: the locale of the request, or the one it is switched to. */
    public const string LOCALE_SWITCHER = 'translation.locale_switcher';

    /**
     * @param list<string>                $protected
     * @param array<string, list<string>> $locales      fixer names by locale; an empty list keeps the preset
     * @param string|null                 $ujo          the locale of the UJO filter, `''` for the default one; none when null
     * @param list<string>                $experimental the experimental hyphenation patterns served
     */
    public static function register(ContainerBuilder $container, ?string $defaultLocale, bool $hyphenation, array $protected, array $locales, ?string $ujo, array $experimental): void
    {
        $typographers = new Reference(Typographers::class);
        $current = new Reference(self::LOCALE_SWITCHER, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        $container->register(Dictionary::class, Dictionary::class)
            ->setArguments([null, $experimental]);
        $container->register(Typographers::class, Typographers::class)
            ->setArguments([$defaultLocale ?? '%kernel.default_locale%', array_filter($locales), $protected, $hyphenation, new Reference(Dictionary::class)])
            ->setPublic(true);
        $container->register(Typographer::class, Typographer::class)
            ->setFactory([$typographers, 'for'])
            ->setPublic(true);

        foreach (array_keys($locales) as $locale) {
            $container->register(self::TYPOGRAPHER.$locale, Typographer::class)
                ->setFactory([$typographers, 'for'])
                ->setArguments([$locale])
                ->setPublic(true);
            $container->registerAliasForArgument(self::TYPOGRAPHER.$locale, Typographer::class, $locale.' typographer');
        }

        if (class_exists(AsTwigFilter::class)) {
            $container->register(TypographyExtension::class, TypographyExtension::class)
                ->setArguments([$typographers, $current])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        if (null !== $ujo && interface_exists(HtmlFilter::class)) {
            $container->register(self::UJO_FILTER, TypographyHtmlFilter::class)
                ->setArguments([$typographers, '' === $ujo ? null : $ujo, $current]);
        }
    }
}
