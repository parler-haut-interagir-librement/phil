<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\DependencyInjection;

use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\CommentsBundle\Application\AuthorDirectory;
use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Application\MentionRecorder;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch;
use Phil\MentionsBundle\Infrastructure\Comments\CommentMentions;
use Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory;
use Phil\MentionsBundle\Infrastructure\Comments\RecordCommentMentions;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Infrastructure\Twig\MentionsExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * The bundle's services: the store, the pattern and the limits, the extraction, the recorder and its facade, the
 * notification of each new mention, the Twig filter with Twig, the editor's `@` search with the block editor, and the
 * comments' mentions, recorded as each comment is written, edited or deleted unless the configuration says otherwise,
 * and authors' names with the comments bundle.
 */
final class MentionsServices
{
    /**
     * @param array{store: string, entity_manager: string, member_directory: string, pattern: string, limits: array{per_text: int, text_length: int}, notification_type: string, preferences: ?string, comments: array{markdown: bool, record: bool}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryMentionRepository::class, InMemoryMentionRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(MentionRepository::class, InMemoryMentionRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineMentionRepository::class, DoctrineMentionRepository::class)
                ->setArguments([new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager')]);
            $container->setAlias(MentionRepository::class, DoctrineMentionRepository::class)->setPublic(true);
        }

        $container->setAlias(MemberDirectory::class, $config['member_directory']);
        $container->register(MentionPattern::class, MentionPattern::class)
            ->setFactory([MentionPattern::class, 'of'])
            ->setArguments([$config['pattern']]);
        $container->register(MentionLimits::class, MentionLimits::class)
            ->setArguments([$config['limits']['per_text'], $config['limits']['text_length']]);
        $container->register(TextMentions::class, TextMentions::class)
            ->setArguments([new Reference(MentionPattern::class)]);
        $container->register(MentionRecorder::class, MentionRecorder::class)
            ->setArguments([
                new Reference(MentionRepository::class),
                new Reference(MemberDirectory::class),
                new Reference('clock'),
                new Reference(MentionLimits::class),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(Mentions::class, Mentions::class)
            ->setArguments([new Reference(MentionRecorder::class), new Reference(TextMentions::class), new Reference(MentionLimits::class)])
            ->setPublic(true);
        $container->register(MentionLinker::class, MentionLinker::class)
            ->setArguments([new Reference(MemberDirectory::class), new Reference(MentionPattern::class), $config['limits']['per_text']])
            ->setPublic(true);
        $container->register(NotifyMentionedMember::class, NotifyMentionedMember::class)
            ->setArguments([new Reference(MentionsPass::NOTIFICATIONS), new Reference(MentionsPass::PREFERENCES), $config['notification_type']])
            ->addTag('kernel.event_listener', ['event' => MemberMentioned::class]);
        $container->setParameter(MentionsPass::PREFERENCES_PARAMETER, $config['preferences']);

        self::integrations($container, $config['comments']['markdown'], $config['comments']['record']);
    }

    private static function integrations(ContainerBuilder $container, bool $markdown, bool $record): void
    {
        if (class_exists(AsTwigFilter::class)) {
            // Autoconfigured: TwigBundle reads the attribute of its filter and loads the service as a runtime.
            $container->register(MentionsExtension::class, MentionsExtension::class)
                ->setArguments([new Reference(MentionLinker::class)])
                ->setAutoconfigured(true);
        }

        if (interface_exists(ReferenceSearch::class)) {
            $container->register(MemberReferenceSearch::class, MemberReferenceSearch::class)
                ->setArguments([new Reference(MemberDirectory::class)]);
        }

        if (interface_exists(AuthorDirectory::class)) {
            $container->register(MemberAuthorDirectory::class, MemberAuthorDirectory::class)
                ->setArguments([new Reference(MemberDirectory::class)]);
            $container->register(CommentMentions::class, CommentMentions::class)
                ->setArguments([new Reference(Mentions::class), $markdown])
                ->setPublic(true);
        }

        if ($record && class_exists(CommentWritten::class)) {
            $container->register(RecordCommentMentions::class, RecordCommentMentions::class)
                ->setArguments([new Reference(CommentMentions::class)])
                ->addTag('kernel.event_listener', ['event' => CommentWritten::class, 'method' => 'written'])
                ->addTag('kernel.event_listener', ['event' => CommentDeleted::class, 'method' => 'deleted']);
        }
    }
}
