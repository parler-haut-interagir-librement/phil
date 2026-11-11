<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\DependencyInjection;

use InvalidArgumentException;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension;
use Phil\MarkupBundle\Infrastructure\Typography\SmartPunctuationCompiler;
use Phil\Typography\Typographers;
use Phil\Ujo\Html\HtmlRenderer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Twig\Attribute\AsTwigFilter;

/**
 * The UJO renderer, the compiler and the purifier of `phil/markup`, aliased by their interfaces so an application can
 * replace either, the readers of the formats add-ons bring, `Markup`, the enabled formats and their levels, the converter into UJO, the smart punctuation when
 * `phil/typography` is installed, and the form type and the Twig filters when Symfony Form and Twig are installed.
 */
final class MarkupServices
{
    /** @var list<string> */
    public const array LEVELS = ['basic', 'forum', 'all'];

    /** The compiler that decorates `MarkupCompiler` with smart punctuation, when `phil/typography` is installed. */
    public const string SMART_PUNCTUATION = 'phil_markup.smart_punctuation';

    /** The typographers of the smart punctuation, when `phil/typography-bundle` registers none. */
    public const string TYPOGRAPHERS = 'phil_markup.typographers';

    /** The `LocaleSwitcher` of Symfony's translator: the locale of the request, or the one it is switched to. */
    public const string LOCALE_SWITCHER = 'translation.locale_switcher';

    /** What names a format an add-on brings: a lower-case letter, then up to 15 lower-case letters, digits or hyphens. */
    private const string NAME = '/^[a-z][a-z0-9-]{0,15}$/D';

    /**
     * A configuration whose formats are known — the built-in ones and those added —, whose levels are given by known
     * formats, and whose default format is enabled; its added formats named as `phil/markup` names a format.
     *
     * @param array{formats: list<string>, default_format: string|null, levels: array<string, string>, added_formats: array<string, array{label: string, reader: string}>} $config
     *
     * @return array{formats: list<string>, default_format: string|null, levels: array<string, string>, added_formats: array<string, array{label: string, reader: string}>}
     *
     * @throws InvalidArgumentException when it is not
     */
    public static function checked(array $config): array
    {
        $added = array_keys($config['added_formats']);
        $known = [...MarkupFormat::values(), ...$added];
        $taken = array_filter($added, static fn (string $name): bool => 1 !== preg_match(self::NAME, $name) || null !== MarkupFormat::tryFrom($name));
        $unknown = array_diff($config['formats'], $known);
        $levels = array_diff(array_keys($config['levels']), $known);
        $default = $config['default_format'];

        return match (true) {
            [] !== $taken => throw new InvalidArgumentException(\sprintf('An added format is named by 1 to 16 lower-case letters, digits and hyphens, starting with a letter, and is not a built-in format; "%s" is not.', implode('", "', $taken))),
            [] !== $unknown => throw new InvalidArgumentException(\sprintf('The formats are among %s; "%s" is not.', implode(', ', $known), implode('", "', $unknown))),
            [] !== $levels => throw new InvalidArgumentException(\sprintf('The levels are given by format, one of %s; "%s" is not.', implode(', ', $known), implode('", "', $levels))),
            null !== $default && !\in_array($default, [] === $config['formats'] ? $known : $config['formats'], true) => throw new InvalidArgumentException(\sprintf('The default format must be one of the enabled formats; "%s" is not.', $default)),
            default => $config,
        };
    }

    /**
     * @param list<string>|null                                   $mediaHosts the hosts images may come from, `null` for any
     * @param list<string>                                        $formats    the enabled formats, in the order a form offers them; every one when empty
     * @param string|null                                         $default    the format taken when none is named; the first enabled when `null`
     * @param array<string, string>                               $levels     the highest level of a format, by format
     * @param string|false|null                                   $smart      the locale of the smart punctuation, `null` for the current one; none when `false`
     * @param array<string, array{label: string, reader: string}> $added      the formats add-ons bring, by name: their label, and the service of their `ReaderProvider`
     */
    public static function register(ContainerBuilder $container, string $level, ?array $mediaHosts = null, array $formats = [], ?string $default = null, array $levels = [], string|false|null $smart = false, array $added = []): void
    {
        $enabled = [] === $formats ? [...MarkupFormat::values(), ...array_keys($added)] : $formats;

        $container->register(HtmlRenderer::class, HtmlRenderer::class)->setFactory([HtmlRenderer::class, 'default']);
        $container->register(DefaultCompiler::class, DefaultCompiler::class)->setArguments([
            new Reference(HtmlRenderer::class),
            array_map(static fn (array $format): Reference => new Reference($format['reader']), $added),
        ]);
        $container->setAlias(MarkupCompiler::class, DefaultCompiler::class);
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)
            ->setFactory([SanitizerPurifier::class, 'defaultPolicies'])
            ->setArguments([$mediaHosts]);
        $container->setParameter(MediaHostsPass::PARAMETER, $mediaHosts);
        $container->setAlias(HtmlPurifier::class, SanitizerPurifier::class);
        $container->register(Markup::class, Markup::class)
            ->setArguments([new Reference(MarkupCompiler::class), new Reference(HtmlPurifier::class)])
            ->setPublic(true);
        $container->register(MarkupFormats::class, MarkupFormats::class)
            ->setArguments([$enabled, $default ?? $enabled[0], Level::from($level), array_map(Level::from(...), $levels), array_map(static fn (array $format): string => $format['label'], $added)])
            ->setPublic(true);
        $container->register(UjoConverter::class, UjoConverter::class)
            ->setArguments([new Reference(DefaultCompiler::class), new Reference(MarkupFormats::class)])
            ->setPublic(true);

        if (false !== $smart && class_exists(Typographers::class)) {
            $container->register(self::SMART_PUNCTUATION, SmartPunctuationCompiler::class)
                ->setDecoratedService(MarkupCompiler::class)
                ->setArguments([
                    new Reference(self::SMART_PUNCTUATION.'.inner'),
                    new Reference(Typographers::class),
                    $enabled,
                    $smart,
                    new Reference(self::LOCALE_SWITCHER, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                ]);
        }

        if (class_exists(AbstractType::class)) {
            $container->register(MarkupFormatType::class, MarkupFormatType::class)
                ->setArguments([new Reference(MarkupFormats::class)])
                ->addTag('form.type');
        }

        if (class_exists(AsTwigFilter::class)) {
            $container->register(MarkupExtension::class, MarkupExtension::class)
                ->setArguments([new Reference(Markup::class), new Reference(HtmlPurifier::class), new Reference(MarkupFormats::class), new Reference(UjoConverter::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }
    }
}
