<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit;

use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\Purifiers;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MarkupServices;
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

    /**
     * The ceiling of an HTML purified comes back with the hosts, when the configuration gave one.
     */
    public function testTheCeilingReachesAnAdapterAnotherBundleRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(MediaHostsPass::PARAMETER, ['cdn.example.com']);
        $container->setParameter(MediaHostsPass::MAX_BYTES_PARAMETER, 30);
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
        $container->register('b', HtmlPurifier::class)->setFactory([Purifiers::class, 'defaultPolicies']);

        new MediaHostsPass()->process($container);

        self::assertSame([['cdn.example.com'], '$maxBytes' => 30], $container->getDefinition(SanitizerPurifier::class)->getArguments());
        self::assertSame([['cdn.example.com'], '$maxBytes' => 30], $container->getDefinition('b')->getArguments());
        self::assertSame('phil_markup.purifier_max_bytes', MediaHostsPass::MAX_BYTES_PARAMETER);

        $container->getDefinition(SanitizerPurifier::class)->setPublic(true);
        $container->compile(true);
        $purifier = $container->get(SanitizerPurifier::class);
        self::assertInstanceOf(SanitizerPurifier::class, $purifier);
        $this->expectException(InputTooLarge::class);
        $purifier->purify(str_repeat('a', 31), Level::All);
    }

    public function testTheHostsReachEveryPurifierOfTheDefaultPolicies(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(MediaHostsPass::PARAMETER, ['cdn.example.com']);
        $container->register('a', AmmoniaPurifier::class)->setFactory([AmmoniaPurifier::class, 'defaultPolicies']);
        $container->register('b', HtmlPurifier::class)->setFactory([Purifiers::class, 'defaultPolicies']);
        $container->register('c', AmmoniaPurifier::class)->setFactory([Purifiers::class, 'defaultPolicies']);
        $container->register('d', HtmlPurifier::class)->setFactory([AmmoniaPurifier::class, 'other']);

        new MediaHostsPass()->process($container);

        self::assertSame([['cdn.example.com']], $container->getDefinition('a')->getArguments());
        self::assertSame([['cdn.example.com']], $container->getDefinition('b')->getArguments());
        self::assertSame([], $container->getDefinition('c')->getArguments());
        self::assertSame([], $container->getDefinition('d')->getArguments());
    }

    /**
     * A bundle that points `HtmlPurifier` back to the sanitizer after this one loses it to the purifier chosen.
     */
    public function testThePurifierChosenKeepsTheAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(MediaHostsPass::PARAMETER, null);
        $container->register(MarkupServices::PURIFIER, HtmlPurifier::class)->setFactory([Purifiers::class, 'defaultPolicies']);
        $container->register(SanitizerPurifier::class, SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
        $container->setAlias(HtmlPurifier::class, SanitizerPurifier::class);

        new MediaHostsPass()->process($container);
        self::assertSame(MarkupServices::PURIFIER, (string) $container->getAlias(HtmlPurifier::class));

        $container->register('app.purifier', SanitizerPurifier::class);
        $container->setAlias(HtmlPurifier::class, 'app.purifier');
        new MediaHostsPass()->process($container);
        self::assertSame('app.purifier', (string) $container->getAlias(HtmlPurifier::class), 'A purifier of the application is kept.');

        $without = new ContainerBuilder();
        $without->setParameter(MediaHostsPass::PARAMETER, null);
        $without->register(SanitizerPurifier::class, SanitizerPurifier::class);
        $without->setAlias(HtmlPurifier::class, SanitizerPurifier::class);
        new MediaHostsPass()->process($without);
        self::assertSame(SanitizerPurifier::class, (string) $without->getAlias(HtmlPurifier::class), 'The sanitizer chosen is kept.');

        $unaliased = new ContainerBuilder();
        $unaliased->setParameter(MediaHostsPass::PARAMETER, null);
        $unaliased->register(MarkupServices::PURIFIER, HtmlPurifier::class);
        new MediaHostsPass()->process($unaliased);
        self::assertFalse($unaliased->hasAlias(HtmlPurifier::class));
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
