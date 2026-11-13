<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\DependencyInjection;

use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\DefaultCommentPolicy;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineThreadRepository;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\Infrastructure\Http\CommentsController;
use Phil\CommentsBundle\Infrastructure\Http\CommentsRequest;
use Phil\CommentsBundle\Infrastructure\Http\DiscussionJson;
use Phil\CommentsBundle\Infrastructure\Http\PageController;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent;
use Phil\CommentsBundle\Infrastructure\Twig\KitComponents;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the stores, the policy the configuration names or the default one, the comments — which tell
 * the event dispatcher of each comment written, edited or deleted, when there is one —, the check
 * of the controller's requests on FrameworkBundle's CSRF token manager, the endpoints and the controller's
 * configuration; and, with Symfony UX Twig Component, the comments on a whole page drawn by the server.
 */
final class CommentsServices
{
    public const string MENTIONS_BUNDLE = 'PhilMentionsBundle';

    public const string MENTION_SEARCH_ROUTE = 'phil_block_editor_mentions';

    public const string TWIG_COMPONENT_BUNDLE = 'TwigComponentBundle';

    /**
     * @param array{store: string, entity_manager: string, author_provider: ?string, policy: ?string, author_directory: ?string, aria_roles: bool, kit_subdirectory: ?string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryThreadRepository::class, InMemoryThreadRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->register(InMemoryCommentRepository::class, InMemoryCommentRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(ThreadRepository::class, InMemoryThreadRepository::class)->setPublic(true);
            $container->setAlias(CommentRepository::class, InMemoryCommentRepository::class)->setPublic(true);
        } else {
            $entityManager = new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager');
            $container->register(DoctrineThreadRepository::class, DoctrineThreadRepository::class)->setArguments([$entityManager]);
            $container->register(DoctrineCommentRepository::class, DoctrineCommentRepository::class)->setArguments([$entityManager]);
            $container->setAlias(ThreadRepository::class, DoctrineThreadRepository::class)->setPublic(true);
            $container->setAlias(CommentRepository::class, DoctrineCommentRepository::class)->setPublic(true);
        }

        if (null === $config['policy']) {
            $container->register(DefaultCommentPolicy::class, DefaultCommentPolicy::class);
            $container->setAlias(CommentPolicy::class, DefaultCommentPolicy::class);
        } else {
            $container->setAlias(CommentPolicy::class, $config['policy']);
        }

        $container->register(Comments::class, Comments::class)
            ->setArguments([
                new Reference(ThreadRepository::class),
                new Reference(CommentRepository::class),
                new Reference('clock'),
                new Reference(CommentPolicy::class),
                null,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(DiscussionJson::class, DiscussionJson::class)
            ->setArguments([new Reference(CommentPolicy::class), self::service($config['author_directory'])]);
        $container->register(CommentsRequest::class, CommentsRequest::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(CommentsController::class, CommentsController::class)
            ->setArguments([
                new Reference(Comments::class),
                new Reference(DiscussionJson::class),
                new Reference(CommentsRequest::class),
                self::service($config['author_provider']),
                null !== $config['author_provider'] && null !== $config['policy'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ClientConfig::class, ClientConfig::class)
            ->setArguments([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(CommentsRequest::class), self::mentionSearchRoute($container), $config['aria_roles']])
            ->setPublic(true);

        if (self::isEnabled($container, self::TWIG_COMPONENT_BUNDLE)) {
            self::registerPage($container, $config);
        }
    }

    /**
     * The comments on a whole page drawn by the server: the names of the kit's recipes, the guard of the plain forms,
     * the controller they post to and the component.
     *
     * @param array{store: string, entity_manager: string, author_provider: ?string, policy: ?string, author_directory: ?string, aria_roles: bool, kit_subdirectory: ?string} $config
     */
    private static function registerPage(ContainerBuilder $container, array $config): void
    {
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(PageForm::class, PageForm::class)
            ->setArguments([new Reference('security.csrf.token_manager'), new Reference('uri_signer'), new Reference('router')]);
        $container->register(PageController::class, PageController::class)
            ->setArguments([
                new Reference(Comments::class),
                new Reference(PageForm::class),
                new Reference('twig'),
                new Reference('translator'),
                self::service($config['author_provider']),
                null !== $config['author_provider'] && null !== $config['policy'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(PageCommentsComponent::class, PageCommentsComponent::class)
            ->setArguments([
                new Reference(Comments::class),
                new Reference(CommentPolicy::class),
                new Reference(DiscussionJson::class),
                new Reference(PageForm::class),
                new Reference(ClientConfig::class),
                new Reference(KitComponents::class),
                new Reference('request_stack'),
                self::service($config['author_provider']),
            ])
            ->setAutoconfigured(true);
    }

    /**
     * The route of the member search the `@` autocomplete calls, when `phil/mentions-bundle` is enabled.
     */
    private static function mentionSearchRoute(ContainerBuilder $container): ?string
    {
        return self::isEnabled($container, self::MENTIONS_BUNDLE) ? self::MENTION_SEARCH_ROUTE : null;
    }

    /**
     * Whether the kernel enables a bundle; read from the kernel's parameter, since the container an extension loads
     * into knows no other extension.
     */
    private static function isEnabled(ContainerBuilder $container, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of whatever
        // application analyses the bundle, not from the kernel that loads it.
        $bundles = $container->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }

    private static function service(?string $id): ?Reference
    {
        return null === $id ? null : new Reference($id);
    }
}
