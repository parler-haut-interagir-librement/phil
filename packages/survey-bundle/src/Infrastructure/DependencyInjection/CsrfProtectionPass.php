<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SurveyBundle\Infrastructure\Http\SurveyGuard;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Stops the container from building when the bundle's pages are on and Symfony's CSRF protection is off: the forms
 * would otherwise be taken from any site.
 */
final readonly class CsrfProtectionPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SurveyGuard::class) || $container->has(self::TOKEN_MANAGER)) {
            return;
        }

        throw new LogicException(\sprintf('phil/survey-bundle checks its forms with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true: the token "%s" is stateless and needs no session.', SurveyGuard::TOKEN_ID));
    }
}
