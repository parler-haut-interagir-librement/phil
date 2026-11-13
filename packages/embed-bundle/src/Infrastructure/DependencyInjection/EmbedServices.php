<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\DependencyInjection;

use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Phil\EmbedBundle\Infrastructure\Console\IssueTokenCommand;
use Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener;
use Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController;
use Phil\EmbedBundle\Infrastructure\Http\HeaderFraming;
use Phil\EmbedBundle\Infrastructure\Twig\EmbedExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The bundle's services: the keys and the embeds of the configuration, the token codec and its facade, the codes,
 * the listener of embed pages, the framing — the bundle's own header, until FramingPass puts
 * phil/browser-security-bundle in its place —, the script's controller, the Twig functions and the token command.
 *
 * @phpstan-type Config array{
 *     keys: list<array{id: string, secret: string}>,
 *     allow_localhost: bool,
 *     framing: string,
 *     script_max_age: int,
 *     embeds: array<string, array{origins: list<string>, themes: list<string>, default_theme: ?string, ttl: ?int}>
 * }
 */
final class EmbedServices
{
    public const string FRAMING_AUTO = 'auto';

    public const string FRAMING_BROWSER_SECURITY = 'browser_security';

    public const string FRAMING_HEADER = 'header';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config, string $scripts): void
    {
        $localhost = $config['allow_localhost'];
        $keys = array_map(static fn (array $key): Definition => new Definition(SigningKey::class, [$key['id'], $key['secret']])->setFactory([SigningKey::class, 'of']), $config['keys']);
        $container->register(EmbedTokenCodec::class, EmbedTokenCodec::class)->setArguments([$keys, new Reference('clock'), $localhost]);

        $embeds = [];

        foreach ($config['embeds'] as $name => $embed) {
            $origins = array_map(static fn (string $origin): Definition => new Definition(Origin::class, [$origin, $localhost])->setFactory([Origin::class, 'fromString']), $embed['origins']);
            $embeds[] = new Definition(Embed::class, [$name, $origins, $embed['themes'], $embed['default_theme'], $embed['ttl']])->setFactory([Embed::class, 'of']);
        }

        $container->register(Embeds::class, Embeds::class)->setArguments([$embeds]);
        $container->register(EmbedTokens::class, EmbedTokens::class)
            ->setArguments([new Reference(EmbedTokenCodec::class), new Reference(Embeds::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(EmbedCodes::class, EmbedCodes::class)
            ->setArguments([new Reference(EmbedTokens::class), new Reference('router'), $localhost])
            ->setPublic(true);

        $container->register(HeaderFraming::class, HeaderFraming::class)
            ->addTag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onResponse', 'priority' => -1024]);
        // FramingPass aliases the framing once every bundle is loaded.
        $container->setParameter(FramingPass::PARAMETER, $config['framing']);
        $container->setAlias(FramingPolicy::class, HeaderFraming::class);
        $container->register(EmbedRequestListener::class, EmbedRequestListener::class)
            ->setArguments([new Reference(EmbedTokens::class), new Reference(FramingPolicy::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $localhost])
            ->addTag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER, 'priority' => -16]);

        $container->register(EmbedScriptController::class, EmbedScriptController::class)
            ->setArguments([$scripts, null, $config['script_max_age']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(EmbedExtension::class, EmbedExtension::class)
            ->setArguments([new Reference('request_stack'), new Reference(EmbedCodes::class), $localhost])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(IssueTokenCommand::class, IssueTokenCommand::class)
            ->setArguments([new Reference(EmbedTokens::class), $localhost])
            ->addTag('console.command');
    }
}
