<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * A Twig environment with the bundle's templates under `@PhilMedia`, `path()` and `media_url()`.
 */
final class Twig
{
    public static function environment(UrlGenerator $urls, MediaExtension $media, ?string $forms = null): Environment
    {
        $loader = new FilesystemLoader(null === $forms ? [] : [$forms]);
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilMedia');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new RoutingExtension($urls));
        $twig->addExtension(new AttributeExtension(MediaExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([MediaExtension::class => static fn (): MediaExtension => $media]));

        return $twig;
    }
}
