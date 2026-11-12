<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Markup;

use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\Editing\AuthorRawHtml;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlAuthorPass;
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlRole;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\ContentBundle\PhilContentBundle;
use Phil\ContentBundle\Tests\Unit\Fixture\SwitchableRawHtml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(RawHtmlAuthorPass::class)]
final class RawHtmlAuthorPassTest extends TestCase
{
    /**
     * With the trusted role, the role is wrapped in `AuthorRawHtml`, which the data mapper and the working copies read,
     * and the converter learns that raw HTML is configured.
     */
    public function testTheTrustedRoleIsCheckedOnTheAuthor(): void
    {
        $container = $this->load(['raw_html_role' => 'ROLE_EDITOR']);

        new RawHtmlAuthorPass()->process($container);

        self::assertEquals([new Reference(RawHtmlPermission::class)], $container->getDefinition(AuthorRawHtml::class)->getArguments());
        self::assertSame(RawHtmlRole::class, (string) $container->getAlias(RawHtmlPermission::class));
        self::assertEquals(new Reference(AuthorRawHtml::class), $container->getDefinition(BodyDataMapper::class)->getArgument('$rawHtml'));
        self::assertEquals(new Reference(AuthorRawHtml::class), $container->getDefinition(WorkingCopies::class)->getArgument('$rawHtml'));
        self::assertTrue($container->getDefinition(UjoBodyConverter::class)->getArgument('$rawHtml'));
    }

    /**
     * An application that decides by something else than a role aliases the port to its own service: its permission
     * is the one checked on the author.
     */
    public function testThePermissionOfTheApplicationIsCheckedOnTheAuthor(): void
    {
        $container = $this->load();
        $container->register('app.raw_html', SwitchableRawHtml::class);
        $container->setAlias(RawHtmlPermission::class, 'app.raw_html');

        new RawHtmlAuthorPass()->process($container);

        self::assertEquals([new Reference(RawHtmlPermission::class)], $container->getDefinition(AuthorRawHtml::class)->getArguments());
        self::assertTrue($container->getDefinition(UjoBodyConverter::class)->getArgument('$rawHtml'));
    }

    public function testWithoutAPermissionNothingChanges(): void
    {
        $container = $this->load();

        new RawHtmlAuthorPass()->process($container);

        self::assertFalse($container->hasDefinition(AuthorRawHtml::class));
        self::assertArrayNotHasKey('$rawHtml', $container->getDefinition(BodyDataMapper::class)->getArguments());
        self::assertArrayNotHasKey('$rawHtml', $container->getDefinition(WorkingCopies::class)->getArguments());
        self::assertSame([], $container->getDefinition(UjoBodyConverter::class)->getArguments());
    }

    /**
     * A container that left out one of the services the pass sets is not given it.
     */
    public function testAServiceTheContainerDoesNotHaveIsNotRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->register(RawHtmlPermission::class, SwitchableRawHtml::class);

        new RawHtmlAuthorPass()->process($container);

        self::assertTrue($container->hasDefinition(AuthorRawHtml::class));
        self::assertFalse($container->hasDefinition(BodyDataMapper::class));
        self::assertFalse($container->hasDefinition(WorkingCopies::class));
        self::assertFalse($container->hasDefinition(UjoBodyConverter::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilContentBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);
        // Registered with DoctrineBundle, which this container leaves out.
        $container->register(WorkingCopies::class, WorkingCopies::class)->setAutowired(true);

        return $container;
    }
}
