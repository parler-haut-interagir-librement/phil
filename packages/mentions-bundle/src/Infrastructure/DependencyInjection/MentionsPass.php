<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\MentionsBundle\Application\AlwaysNotify;
use Phil\MentionsBundle\Application\Contract\MentionPreferences;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Infrastructure\Settings\SettingsMentionPreferences;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\SettingsBundle\Application\Settings;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires what depends on the other bundles of the application: the notification of new mentions, only when the
 * notifications bundle is enabled; and the members' preference — the service the configuration names, else the
 * setting of the settings bundle when it is enabled, else every member notified.
 */
final readonly class MentionsPass implements CompilerPassInterface
{
    public const string NOTIFICATIONS = Notifications::class;

    public const string PREFERENCES = MentionPreferences::class;

    public const string PREFERENCES_PARAMETER = 'phil_mentions.preferences';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::NOTIFICATIONS)) {
            $container->removeDefinition(NotifyMentionedMember::class);
        }

        $preferences = $container->hasParameter(self::PREFERENCES_PARAMETER) ? $container->getParameter(self::PREFERENCES_PARAMETER) : null;

        if (\is_string($preferences)) {
            $container->setAlias(self::PREFERENCES, $preferences);
        } elseif ($container->has(Settings::class)) {
            $container->register(SettingsMentionPreferences::class, SettingsMentionPreferences::class)->setArguments([new Reference(Settings::class)]);
            $container->setAlias(self::PREFERENCES, SettingsMentionPreferences::class);
        } else {
            $container->register(AlwaysNotify::class, AlwaysNotify::class);
            $container->setAlias(self::PREFERENCES, AlwaysNotify::class);
        }
    }
}
