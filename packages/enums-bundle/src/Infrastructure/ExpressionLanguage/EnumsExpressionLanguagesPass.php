<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\ExpressionLanguage;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the expression functions to the expression languages of FrameworkBundle that take no provider by tag: the one
 * of controller attributes (`#[Cache]`, `#[RateLimit]`, `#[Lock]`…) and the one of workflow guards. Symfony's
 * `lint:expressions` checks the expressions these languages evaluate with the functions they know, so an expression
 * that calls `enum_from()` there passes the lint and evaluates, and one that misspells it fails the lint.
 */
final readonly class EnumsExpressionLanguagesPass implements CompilerPassInterface
{
    /** The expression languages of FrameworkBundle the functions are given to, when they are registered. */
    public const array EXPRESSION_LANGUAGES = ['controller.expression_language', 'workflow.security.expression_language'];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(EnumsExpressionProvider::class)) {
            return;
        }

        foreach (self::EXPRESSION_LANGUAGES as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->addMethodCall('registerProvider', [new Reference(EnumsExpressionProvider::class)]);
            }
        }
    }
}
