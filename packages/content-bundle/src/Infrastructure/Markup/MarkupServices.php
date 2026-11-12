<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Markup;

use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\Publication\BodyConverter;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\MarkupCompiler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The three ports of the body: `MarkupCompiler` and `HtmlPurifier` of `phil/markup`, each aliased to the library's
 * adapter, and the bundle's `BodyConverter` and `ConversionLosses`, aliased to `UjoBodyConverter`. An application replaces any of them by
 * aliasing the interface to its own service; `phil/markup-bundle` registers the same two adapters.
 *
 * With a trusted role, `RawHtmlRole` checks it on Symfony's authorization checker, `security.authorization_checker`,
 * which SecurityBundle registers, and is the `RawHtmlPermission`: the body data mapper shows the raw HTML of the drafts
 * the users granted it save, and the converter publishes it.
 */
final class MarkupServices
{
    public static function register(ServicesConfigurator $services, ?string $rawHtmlRole): void
    {
        $services->set(DefaultCompiler::class);
        $services->alias(MarkupCompiler::class, DefaultCompiler::class);
        $services->set(SanitizerPurifier::class)
            ->factory([SanitizerPurifier::class, 'defaultPolicies']);
        $services->alias(HtmlPurifier::class, SanitizerPurifier::class);
        $converter = $services->set(UjoBodyConverter::class)->autowire();

        if (null !== $rawHtmlRole) {
            $services->set(RawHtmlRole::class)
                ->args([service('security.authorization_checker'), $rawHtmlRole]);
            $services->alias(RawHtmlPermission::class, RawHtmlRole::class);
            $converter->arg('$rawHtml', service(RawHtmlPermission::class));
        }

        $services->alias(BodyConverter::class, UjoBodyConverter::class);
        $services->alias(ConversionLosses::class, UjoBodyConverter::class);
    }
}
