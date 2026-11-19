<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\DependencyInjection;

use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\ReactionsBundle\Application\ConfiguredVisibility;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;
use Phil\ReactionsBundle\Application\Contract\MemberNames;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Application\Contract\ReactionThrottle;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Application\DeclaredReactionSource;
use Phil\ReactionsBundle\Application\DenyingReactionAccess;
use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Phil\ReactionsBundle\Application\NoCurrentMember;
use Phil\ReactionsBundle\Application\NoMemberNames;
use Phil\ReactionsBundle\Application\PlainEmojiImages;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\ReactionCatalog;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionsPerMember;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Doctrine\DoctrineReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionController;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Infrastructure\Media\MediaEmojiImages;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Messenger\EventsOnTheBus;
use Phil\ReactionsBundle\Infrastructure\Privacy\ReactionsPrivacy;
use Phil\ReactionsBundle\Infrastructure\RateLimiter\SymfonyThrottle;
use Phil\ReactionsBundle\Infrastructure\Security\AuthorizationCheckerAccess;
use Phil\ReactionsBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ReactionsBundle\Infrastructure\Settings\SettingsVisibility;
use Phil\ReactionsBundle\Infrastructure\Twig\Components\ReactionsComponent;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReactionsBundle\Infrastructure\Twig\ReactionsTwig;
use Phil\SettingsBundle\Application\Settings;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extra\Intl\IntlExtension;

/**
 * The bundle's services: each port on what the application names, else on what is installed, else on the safe default.
 */
final class ReactionsServices
{
    public const string SOURCE_TAG = 'phil_reactions.source';

    /** twig/intl-extra's extension, registered when TwigExtraBundle is not there to register it. */
    public const string INTL_EXTENSION = 'phil_reactions.twig.intl_extension';

    private const string EXPORTER_TAG = 'phil_privacy.exporter';

    private const string ERASER_TAG = 'phil_privacy.eraser';

    private const string MEMBER_REQUIRED = 'The option "phil_reactions.current_member" is required with Symfony Security: name the CurrentMember service that gives the stable identifier of a member, never an email address. "'.TokenCurrentMember::class.'" gives the user identifier of the security token, for an application whose user identifiers are stable and never contain "@".';

    private function __construct()
    {
    }

    /**
     * @param array{
     *     store: string,
     *     connection: string,
     *     unicode: list<array<string, mixed>>,
     *     custom: list<array<string, mixed>>,
     *     order: list<string>,
     *     visibility: array{panel: string, counts: string, names: string},
     *     access: ?string,
     *     current_member: ?string,
     *     member_names: ?string,
     *     visibility_policy: ?string,
     *     rate_limiter: string,
     *     messenger: bool,
     *     kit_subdirectory: ?string,
     *     per_member: int,
     * } $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::repository($container, $config['store'], $config['connection']);
        self::ports($container, $config);
        self::catalog($container, $config);

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(SymfonyThrottle::class, SymfonyThrottle::class)->setArguments([new Reference($config['rate_limiter']), new Reference('clock')]);
        $container->setAlias(ReactionThrottle::class, SymfonyThrottle::class);

        $container->register(ReactionsPerMember::class, ReactionsPerMember::class)->setArguments([$config['per_member']]);
        $container->register(Reactions::class, Reactions::class)
            ->setArguments([
                new Reference(ReactionRepository::class),
                new Reference(ReactionCatalog::class),
                new Reference(ReactionAccess::class),
                new Reference(ReactionThrottle::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ReactionsPerMember::class),
            ])
            ->setPublic(true);
        $container->register(ReactionBoard::class, ReactionBoard::class)
            ->setArguments([
                new Reference(ReactionRepository::class),
                new Reference(ReactionCatalog::class),
                new Reference(ReactionAccess::class),
                new Reference(CurrentMember::class),
                new Reference(VisibilityPolicy::class),
                new Reference(MemberNames::class),
                new Reference('translator'),
                new Reference(ReactionsPerMember::class),
            ])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);

        $container->register(ReactionGuard::class, ReactionGuard::class)->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER), new Reference('uri_signer'), new Reference('router')]);
        $container->register(ReactionController::class, ReactionController::class)
            ->setArguments([new Reference(Reactions::class), new Reference(CurrentMember::class), new Reference(ReactionGuard::class), new Reference('twig'), new Reference('translator')])
            ->setPublic(true);
        $container->register(ReactionsComponent::class, ReactionsComponent::class)
            ->setArguments([new Reference(Reactions::class), new Reference(ReactionBoard::class), new Reference(CurrentMember::class), new Reference(ReactionGuard::class), new Reference(KitComponents::class), new Reference('request_stack'), new Reference('translator')])
            ->setAutowired(true)
            ->setAutoconfigured(true);
        $container->register(ReactionsTwig::class, ReactionsTwig::class)
            ->setArguments([new Reference(ReactionBoard::class)])
            ->setAutoconfigured(true);

        // format_list, which writes the names of those who reacted in the language of the page.
        if (!$container->hasExtension('twig_extra')) {
            $container->register(self::INTL_EXTENSION, IntlExtension::class)->addTag('twig.extension');
        }

        if ($config['messenger']) {
            $container->register(EventsOnTheBus::class, EventsOnTheBus::class)
                ->setArguments([new Reference('messenger.default_bus')])
                ->addTag('kernel.event_listener', ['event' => ReactionAdded::class])
                ->addTag('kernel.event_listener', ['event' => ReactionRemoved::class]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ReactionsPrivacy::class, ReactionsPrivacy::class)
                ->setArguments([new Reference(ReactionRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }

    private static function repository(ContainerBuilder $container, string $store, string $connection): void
    {
        if ('memory' === $store) {
            $container->register(InMemoryReactionRepository::class, InMemoryReactionRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(ReactionRepository::class, InMemoryReactionRepository::class)->setPublic(true);

            return;
        }

        $container->register(DoctrineReactionRepository::class, DoctrineReactionRepository::class)->setArguments([new Reference('doctrine.dbal.'.$connection.'_connection')]);
        $container->setAlias(ReactionRepository::class, DoctrineReactionRepository::class)->setPublic(true);
    }

    /**
     * @param array{visibility: array{panel: string, counts: string, names: string}, access: ?string, current_member: ?string, member_names: ?string, visibility_policy: ?string, ...} $config
     */
    private static function ports(ContainerBuilder $container, array $config): void
    {
        $hasSecurity = $container->hasExtension('security');

        if (null === $config['access']) {
            $class = $hasSecurity ? AuthorizationCheckerAccess::class : DenyingReactionAccess::class;
            $container->register($class, $class)->setArguments($hasSecurity ? [new Reference('security.authorization_checker')] : []);
        }

        $container->setAlias(ReactionAccess::class, $config['access'] ?? ($hasSecurity ? AuthorizationCheckerAccess::class : DenyingReactionAccess::class));
        $container->setAlias(CurrentMember::class, self::currentMember($container, $config['current_member'], $hasSecurity));

        $container->register(NoMemberNames::class, NoMemberNames::class);
        $container->setAlias(MemberNames::class, $config['member_names'] ?? NoMemberNames::class);

        $container->register(ConfiguredVisibility::class, ConfiguredVisibility::class)->setArguments([$config['visibility']['panel'], $config['visibility']['counts'], $config['visibility']['names']]);
        $default = ConfiguredVisibility::class;

        if ($container->hasExtension('phil_settings')) {
            $container->register(SettingsVisibility::class, SettingsVisibility::class)->setArguments([new Reference(ConfiguredVisibility::class), new Reference(Settings::class)]);
            $default = SettingsVisibility::class;
        }

        $container->setAlias(VisibilityPolicy::class, $config['visibility_policy'] ?? $default);
    }

    /**
     * The site names the service giving the stable identifier of a member: with Symfony Security, a member exists, and
     * the user identifier of the token may be an email address, so nothing is chosen for the site.
     *
     * @throws InvalidConfigurationException when Security is there and no service is named
     */
    private static function currentMember(ContainerBuilder $container, ?string $service, bool $hasSecurity): string
    {
        if (null === $service && $hasSecurity) {
            throw new InvalidConfigurationException(self::MEMBER_REQUIRED);
        }

        if (null === $service) {
            $container->register(NoCurrentMember::class, NoCurrentMember::class);

            return NoCurrentMember::class;
        }

        if (TokenCurrentMember::class === $service && $hasSecurity) {
            $container->register(TokenCurrentMember::class, TokenCurrentMember::class)->setArguments([new Reference('security.token_storage')]);
        }

        return $service;
    }

    /**
     * @param array{unicode: list<array<string, mixed>>, custom: list<array<string, mixed>>, order: list<string>, ...} $config
     */
    private static function catalog(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(ReactionSource::class)->addTag(self::SOURCE_TAG);

        foreach (['unicode' => $config['unicode'], 'site' => $config['custom']] as $name => $reactions) {
            $container->register('phil_reactions.source.'.$name, DeclaredReactionSource::class)
                ->setArguments([$name, 'source.'.$name, $reactions])
                ->addTag(self::SOURCE_TAG);
        }

        if ($container->hasExtension('phil_media')) {
            $container->register(MediaEmojiImages::class, MediaEmojiImages::class)->setArguments([new Reference(MediaRepository::class), new Reference(MediaExtension::class)]);
            $container->setAlias(EmojiImages::class, MediaEmojiImages::class);
        } else {
            $container->register(PlainEmojiImages::class, PlainEmojiImages::class);
            $container->setAlias(EmojiImages::class, PlainEmojiImages::class);
        }

        $container->register(ReactionCatalog::class, ReactionCatalog::class)
            ->setArguments([new TaggedIteratorArgument(self::SOURCE_TAG), new Reference(EmojiImages::class), $config['order']])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);
    }
}
