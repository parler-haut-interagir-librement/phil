<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Phil\MentionsBundle\Application\AlwaysNotify;
use Phil\MentionsBundle\Application\Contract\MentionPreferences;
use Phil\MentionsBundle\Infrastructure\DependencyInjection\MentionsPass;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Infrastructure\Settings\SettingsMentionPreferences;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\SettingsBundle\Application\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(MentionsPass::class)]
final class MentionsPassTest extends TestCase
{
    public function testWithoutTheOtherBundlesEveryMemberIsNotifiedOfNothing(): void
    {
        $container = $this->container();

        new MentionsPass()->process($container);

        self::assertFalse($container->hasDefinition(NotifyMentionedMember::class));
        self::assertSame(AlwaysNotify::class, (string) $container->getAlias(MentionPreferences::class));
        self::assertTrue($container->hasDefinition(AlwaysNotify::class));
        self::assertFalse($container->hasDefinition(SettingsMentionPreferences::class));
    }

    public function testWithNotificationsAndSettings(): void
    {
        $container = $this->container();
        $container->register(Notifications::class);
        $container->register(Settings::class);

        new MentionsPass()->process($container);

        self::assertTrue($container->hasDefinition(NotifyMentionedMember::class));
        self::assertSame(SettingsMentionPreferences::class, (string) $container->getAlias(MentionPreferences::class));
        self::assertEquals([new Reference(Settings::class)], $container->getDefinition(SettingsMentionPreferences::class)->getArguments());
        self::assertFalse($container->hasDefinition(AlwaysNotify::class));
    }

    public function testThePreferencesOfTheConfigurationComeFirst(): void
    {
        $container = $this->container('app.preferences');
        $container->register(Settings::class);

        new MentionsPass()->process($container);

        self::assertSame('app.preferences', (string) $container->getAlias(MentionPreferences::class));
        self::assertFalse($container->hasDefinition(SettingsMentionPreferences::class));
        self::assertFalse($container->hasDefinition(AlwaysNotify::class));

        $bare = new ContainerBuilder();
        new MentionsPass()->process($bare);
        self::assertSame(AlwaysNotify::class, (string) $bare->getAlias(MentionPreferences::class));
    }

    private function container(?string $preferences = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(NotifyMentionedMember::class, NotifyMentionedMember::class);
        $container->setParameter(MentionsPass::PREFERENCES_PARAMETER, $preferences);

        return $container;
    }
}
