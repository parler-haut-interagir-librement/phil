<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use Phil\DataTableBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\DataTableBundle\Infrastructure\Twig\CellBlocks;
use Phil\DataTableBundle\Infrastructure\Twig\DataTableExtension;
use Phil\DataTableBundle\Infrastructure\Twig\KitComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(KitComponents::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(DataTableExtension::class)]
final class KitTest extends TestCase
{
    public function testARecipeIsNamedAfterItsSubdirectory(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Button', new KitComponents('/ui/')->name('Button'));
        self::assertSame('ui:kit:Button', new KitDataTable('ui/kit')->kit('Button'));
    }

    public function testTheSubdirectoryOfTheToolkitIsReadUnlessTheConfigurationSaysIt(): void
    {
        $container = new ContainerBuilder();
        $container->register(KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());

        $container->getDefinition(KitComponents::class)->setArguments(['mine']);
        new KitSubdirectoryPass()->process($container);
        self::assertSame(['mine'], $container->getDefinition(KitComponents::class)->getArguments());

        $alone = new ContainerBuilder();
        $alone->register(KitComponents::class)->setArguments([null]);
        new KitSubdirectoryPass()->process($alone);
        self::assertSame([''], $alone->getDefinition(KitComponents::class)->getArguments());

        $nothing = new ContainerBuilder();
        new KitSubdirectoryPass()->process($nothing);
        self::assertFalse($nothing->hasDefinition(KitComponents::class));

        $null = new ContainerBuilder();
        $null->register(KitComponents::class)->setArguments([null]);
        $null->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($null);
        self::assertSame([''], $null->getDefinition(KitComponents::class)->getArguments());
    }
}

/**
 * The extension over a subdirectory, for the test.
 */
final readonly class KitDataTable
{
    public function __construct(
        private string $subdirectory,
    ) {
    }

    public function kit(string $recipe): string
    {
        return new DataTableExtension(new KitComponents($this->subdirectory), new CellBlocks())->kit($recipe);
    }
}
