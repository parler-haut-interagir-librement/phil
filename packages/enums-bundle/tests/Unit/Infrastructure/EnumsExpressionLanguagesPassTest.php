<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Infrastructure\ExpressionLanguage\EnumsExpressionLanguagesPass;
use Phil\EnumsBundle\Infrastructure\ExpressionLanguage\EnumsExpressionProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

#[CoversClass(EnumsExpressionLanguagesPass::class)]
final class EnumsExpressionLanguagesPassTest extends TestCase
{
    public function testTheFunctionsReachTheLanguagesOfControllerAttributesAndWorkflowGuards(): void
    {
        $container = new ContainerBuilder();
        $container->register(EnumsExpressionProvider::class, EnumsExpressionProvider::class);
        $container->register('controller.expression_language', ExpressionLanguage::class);
        $container->register('workflow.security.expression_language', ExpressionLanguage::class);
        $container->register('security.expression_language', ExpressionLanguage::class);

        new EnumsExpressionLanguagesPass()->process($container);

        $call = [['registerProvider', [new Reference(EnumsExpressionProvider::class)]]];
        self::assertEquals($call, $container->getDefinition('controller.expression_language')->getMethodCalls());
        self::assertEquals($call, $container->getDefinition('workflow.security.expression_language')->getMethodCalls());
        self::assertSame([], $container->getDefinition('security.expression_language')->getMethodCalls(), 'security takes the provider by its tag');
    }

    public function testALanguageNotRegisteredIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->register(EnumsExpressionProvider::class, EnumsExpressionProvider::class);
        $container->register('workflow.security.expression_language', ExpressionLanguage::class);

        new EnumsExpressionLanguagesPass()->process($container);

        self::assertFalse($container->hasDefinition('controller.expression_language'));
        self::assertCount(1, $container->getDefinition('workflow.security.expression_language')->getMethodCalls());
    }

    public function testNothingIsGivenWithoutTheProvider(): void
    {
        $container = new ContainerBuilder();
        $container->register('controller.expression_language', ExpressionLanguage::class);

        new EnumsExpressionLanguagesPass()->process($container);

        self::assertSame([], $container->getDefinition('controller.expression_language')->getMethodCalls());
    }
}
