<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use Phil\CommentsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CommentsBundle\Infrastructure\Twig\KitComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(KitComponents::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class KitComponentsTest extends TestCase
{
    public function testARecipeIsNamedUnderItsSubdirectory(): void
    {
        self::assertSame('Card:Title', new KitComponents()->name('Card:Title'));
        self::assertSame('Card', new KitComponents('/')->name('Card'));
        self::assertSame('ui:Card', new KitComponents('ui')->name('Card'));
        self::assertSame('ui:kit:Card:Title', new KitComponents('/ui/kit/')->name('Card:Title'));
    }

    public function testTheSubdirectoryOfTheToolkitIsReadUnlessTheBundleSaysIt(): void
    {
        foreach ([
            [null, 'ui', 'ui'],
            [null, null, ''],
            [null, ['not', 'a', 'string'], ''],
            ['mine', 'ui', 'mine'],
        ] as [$own, $toolkit, $expected]) {
            $container = new ContainerBuilder();
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$own]);

            if (null !== $toolkit) {
                $container->setParameter('.ux_toolkit.components.subdirectory', $toolkit);
            }

            new KitSubdirectoryPass()->process($container);
            self::assertSame($expected, $container->getDefinition(KitComponents::class)->getArgument(0));
        }

        $without = new ContainerBuilder();
        new KitSubdirectoryPass()->process($without);
        self::assertFalse($without->hasDefinition(KitComponents::class));
    }
}
