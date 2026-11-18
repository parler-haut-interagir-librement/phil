<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\InputTooLarge;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MaxBytesPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(MaxBytesPass::class)]
final class MaxBytesPassTest extends TestCase
{
    /**
     * Another bundle registered the compiler after this one, without the ceilings: the pass gives them back, and the
     * compiler built refuses a longer source.
     */
    public function testTheCeilingsReachACompilerAnotherBundleRegistered(): void
    {
        $container = $this->container();
        $container->register(DefaultCompiler::class, DefaultCompiler::class)->setPublic(true);

        new MaxBytesPass()->process($container);

        self::assertSame(['$maxBytes' => 8, '$ujoMaxBytes' => 16], $container->getDefinition(DefaultCompiler::class)->getArguments());

        $container->compile(true);
        $compiler = $container->get(DefaultCompiler::class);
        self::assertInstanceOf(DefaultCompiler::class, $compiler);
        self::assertSame('<p>12345678</p>', $compiler->compile('12345678', 'text'));
        self::assertSame('', $compiler->compile(str_pad('{"blocks":[]}', 16), 'ujo'));

        foreach ([['123456789', 'text'], [str_pad('{"blocks":[]}', 17), 'ujo']] as [$source, $format]) {
            try {
                $compiler->compile($source, $format);
                self::fail(\sprintf('A source of %d bytes should be refused in %s.', \strlen($source), $format));
            } catch (InputTooLarge $inputTooLarge) {
                self::assertSame('ujo' === $format ? 16 : 8, $inputTooLarge->limit);
            }
        }
    }

    public function testTheParametersAreNamed(): void
    {
        self::assertSame(['phil_markup.max_bytes', 'phil_markup.ujo_max_bytes'], [MaxBytesPass::PARAMETER, MaxBytesPass::UJO_PARAMETER]);
    }

    /**
     * The renderer and the readers given by position are kept.
     */
    public function testTheOtherArgumentsAreKept(): void
    {
        $container = $this->container();
        $container->register(DefaultCompiler::class, DefaultCompiler::class)->setArguments([new Reference('app.renderer'), []]);

        new MaxBytesPass()->process($container);

        self::assertEquals([new Reference('app.renderer'), [], '$maxBytes' => 8, '$ujoMaxBytes' => 16], $container->getDefinition(DefaultCompiler::class)->getArguments());
    }

    /**
     * A compiler whose ceilings the application gave by position, built by a factory, or of another class is left
     * alone.
     */
    public function testACompilerBuiltOtherwiseIsLeftAlone(): void
    {
        $container = $this->container();
        $container->register('a', DefaultCompiler::class)->setArguments([null, [], 100]);
        $container->register('b', DefaultCompiler::class)->setArguments([null, [], '$ujoMaxBytes' => 1, 3 => 200]);
        $container->register('c', DefaultCompiler::class)->setFactory(['App\\Compilers', 'build']);
        $container->register('d', stdClass::class);

        new MaxBytesPass()->process($container);

        self::assertSame([null, [], 100], $container->getDefinition('a')->getArguments());
        self::assertSame([null, [], '$ujoMaxBytes' => 1, 3 => 200], $container->getDefinition('b')->getArguments());
        self::assertSame([], $container->getDefinition('c')->getArguments());
        self::assertSame([], $container->getDefinition('d')->getArguments());
    }

    public function testNothingHappensWithoutBothParameters(): void
    {
        foreach ([MaxBytesPass::PARAMETER, MaxBytesPass::UJO_PARAMETER] as $missing) {
            $container = $this->container();
            $container->getParameterBag()->remove($missing);
            $container->register(DefaultCompiler::class, DefaultCompiler::class);

            new MaxBytesPass()->process($container);

            self::assertSame([], $container->getDefinition(DefaultCompiler::class)->getArguments());
        }
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(MaxBytesPass::PARAMETER, 8);
        $container->setParameter(MaxBytesPass::UJO_PARAMETER, 16);

        return $container;
    }
}
