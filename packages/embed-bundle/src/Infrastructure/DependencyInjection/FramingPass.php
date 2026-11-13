<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Infrastructure\BrowserSecurity\BrowserSecurityFraming;
use Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController;
use Phil\EmbedBundle\Infrastructure\Http\HeaderFraming;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Chooses the framing once every bundle is loaded: through phil/browser-security-bundle when its context is a service
 * and the configuration does not ask for the header, else by the bundle's own header; the script's controller then
 * adjusts the same protections.
 */
final readonly class FramingPass implements CompilerPassInterface
{
    public const string PARAMETER = 'phil_embed.framing';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        $framing = $container->getParameter(self::PARAMETER);
        $browserSecurity = $container->has(BrowserSecurityContext::class);
        $useIt = EmbedServices::FRAMING_BROWSER_SECURITY === $framing || (EmbedServices::FRAMING_AUTO === $framing && $browserSecurity);

        if ($useIt) {
            $container->register(BrowserSecurityFraming::class, BrowserSecurityFraming::class)->setArguments([new Reference(BrowserSecurityContext::class)]);
        }

        $container->setAlias(FramingPolicy::class, $useIt ? BrowserSecurityFraming::class : HeaderFraming::class);

        if ($browserSecurity && $container->hasDefinition(EmbedScriptController::class)) {
            $container->getDefinition(EmbedScriptController::class)->setArgument(1, new Reference(BrowserSecurityContext::class));
        }
    }
}
