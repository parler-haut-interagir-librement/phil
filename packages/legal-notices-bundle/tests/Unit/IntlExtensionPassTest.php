<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\IntlExtensionPass;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\LegalNoticesServices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Extra\Html\HtmlExtension;
use Twig\Extra\Intl\IntlExtension;

#[CoversClass(IntlExtensionPass::class)]
final class IntlExtensionPassTest extends TestCase
{
    public function testTheExtensionOfTwigExtraBundleReplacesTheBundlesOwn(): void
    {
        $container = $this->container();
        $container->register('twig.extension.intl', IntlExtension::class)->addTag('twig.extension');

        new IntlExtensionPass()->process($container);

        self::assertFalse($container->hasDefinition(LegalNoticesServices::INTL_EXTENSION));
        self::assertTrue($container->hasDefinition('twig.extension.intl'));
    }

    public function testAnExtensionNamedByItsClassOrAParameterIsFound(): void
    {
        $byClass = $this->container();
        $byClass->register(IntlExtension::class)->addTag('twig.extension');
        new IntlExtensionPass()->process($byClass);
        self::assertFalse($byClass->hasDefinition(LegalNoticesServices::INTL_EXTENSION), 'a service named by its class');

        $byParameter = $this->container();
        $byParameter->setParameter('app.intl_class', IntlExtension::class);
        $byParameter->register('app.intl', '%app.intl_class%')->addTag('twig.extension');
        new IntlExtensionPass()->process($byParameter);
        self::assertFalse($byParameter->hasDefinition(LegalNoticesServices::INTL_EXTENSION), 'a class given by a parameter');
    }

    public function testOtherExtensionsLeaveTheBundlesOwn(): void
    {
        $container = $this->container();
        $container->register('twig.extension.html', HtmlExtension::class)->addTag('twig.extension');
        $container->register('app.not_a_class', stdClass::class)->addTag('twig.extension');
        $container->register('app.intl_untagged', IntlExtension::class);

        new IntlExtensionPass()->process($container);

        self::assertTrue($container->hasDefinition(LegalNoticesServices::INTL_EXTENSION));
    }

    public function testWithoutTheBundlesOwnNothingChanges(): void
    {
        $container = new ContainerBuilder();
        $container->register('twig.extension.intl', IntlExtension::class)->addTag('twig.extension');

        new IntlExtensionPass()->process($container);

        self::assertTrue($container->hasDefinition('twig.extension.intl'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(LegalNoticesServices::INTL_EXTENSION, IntlExtension::class)->addTag('twig.extension');

        return $container;
    }
}
