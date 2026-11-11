<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Infrastructure\DependencyInjection;

use Phil\Migration\Announcements;
use Phil\Migration\Announcer;
use Phil\Migration\Channel;
use Phil\Migration\ChatterPublisher;
use Phil\Migration\Content;
use Phil\Migration\Publisher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Notifier\ChatterInterface;

/**
 * The services of the announcements: the announcements of a run, and, when channels are configured, the announcer
 * and the publisher it sends through, the chatter of `symfony/notifier`.
 *
 * @phpstan-type ChannelConfig array{transport: ?string, locale: string, content: string, title: string, continued: string, heading: string, link: string, max_length: int}
 */
final class AnnouncementServices
{
    public const int MIN_LENGTH = Channel::MIN_LENGTH;

    /** @var list<string> */
    public const array CONTENTS = ['notes', 'news'];

    /**
     * @param array<string, ChannelConfig> $channels
     */
    public static function register(ContainerBuilder $container, array $channels): void
    {
        $container->register(Announcements::class, Announcements::class);

        if ([] === $channels) {
            return;
        }

        $container->register(Publisher::class, ChatterPublisher::class)
            ->setArguments([new Reference(ChatterInterface::class)]);

        $definitions = [];

        foreach ($channels as $name => $channel) {
            $definitions[] = new Definition(Channel::class, [
                $channel['transport'] ?? $name,
                $channel['locale'],
                Content::from($channel['content']),
                $channel['title'],
                $channel['continued'],
                $channel['heading'],
                $channel['link'],
                $channel['max_length'],
            ]);
        }

        $container->register(Announcer::class, Announcer::class)
            ->setArguments([$definitions, new Reference(Publisher::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
    }
}
