<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit;

use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MediaHostsPass;
use Phil\MarkupBundle\PhilMarkupBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(MediaHostsPass::class)]
#[CoversClass(PhilMarkupBundle::class)]
final class MediaHostsPassTest extends TestCase
{
    /**
     * Another bundle registered the adapter after this one, without the hosts: the pass gives them back.
     */
    public function testTheHostsReachAnAdapterAnotherBundleRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(MediaHostsPass::PARAMETER, ['cdn.example.com']);
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);

        new MediaHostsPass()->process($container);

        self::assertSame([['cdn.example.com']], $container->getDefinition(SanitizerPurifier::class)->getArguments());
    }

    public function testAPurifierBuiltOtherwiseIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(MediaHostsPass::PARAMETER, ['cdn.example.com']);
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)->setArguments([['strict' => 'policies']]);

        new MediaHostsPass()->process($container);

        self::assertSame([['strict' => 'policies']], $container->getDefinition(SanitizerPurifier::class)->getArguments());

        $other = new ContainerBuilder();
        $other->setParameter(MediaHostsPass::PARAMETER, ['cdn.example.com']);
        $other->register(SanitizerPurifier::class, stdClass::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
        new MediaHostsPass()->process($other);
        self::assertSame([], $other->getDefinition(SanitizerPurifier::class)->getArguments());
    }

    public function testNothingHappensWithoutTheParameterOrTheAdapter(): void
    {
        $container = new ContainerBuilder();
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
        new MediaHostsPass()->process($container);
        self::assertSame([], $container->getDefinition(SanitizerPurifier::class)->getArguments());

        $other = new ContainerBuilder();
        $other->setParameter(MediaHostsPass::PARAMETER, []);
        $other->register('app.purifier', SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
        new MediaHostsPass()->process($other);
        self::assertSame([[]], $other->getDefinition('app.purifier')->getArguments(), 'Under any identifier.');
    }

    public function testTheBundleAddsThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilMarkupBundle()->build($container);

        self::assertNotSame([], array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof MediaHostsPass));
    }
}
