<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\DependencyInjection;

use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController;
use Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Ujo;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the configured toolset, the normalizers of each toolset on the registry of
 * `phil/ujo-bundle`, the check of the editor's requests on FrameworkBundle's CSRF token manager and on the
 * application's authorization checker, the form field, and the two controllers on the services the configuration
 * names.
 */
final class BlockEditorServices
{
    /**
     * @param array{preset: string, tools: ?list<string>, inline_tools: ?list<string>, colors: list<string>, image_uploader: ?string, mention_search: ?string, link_search: ?string, max_upload_bytes: int, access: ?string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(Toolset::class, new Definition(Toolset::class)
            ->setFactory([Toolset::class, 'preset'])
            ->setArguments([$config['preset'], $config['tools'], $config['inline_tools']]));
        $container->register(ToolsetNormalizers::class, ToolsetNormalizers::class)
            ->setArguments([new Reference(BlockTypeRegistry::class)]);

        $enabled = array_keys(array_filter([
            Endpoints::IMAGE => $config['image_uploader'],
            Endpoints::MENTIONS => $config['mention_search'],
            Endpoints::LINKS => $config['link_search'],
        ], static fn (?string $service): bool => null !== $service));
        $container->register(Endpoints::class, Endpoints::class)
            ->setArguments([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), $enabled]);

        $container->register(EditorRequest::class, EditorRequest::class)
            ->setArguments([
                new Reference('security.csrf.token_manager'),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['access'],
            ]);

        $container->register(BlockEditorType::class, BlockEditorType::class)
            ->setArguments([
                new Reference(Ujo::class),
                new Reference(ToolsetNormalizers::class),
                new Reference(Toolset::class),
                new Reference(Endpoints::class),
                $config['colors'],
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(EditorRequest::class),
            ])
            ->addTag('form.type');

        $container->register(ImageUploadController::class, ImageUploadController::class)
            ->setArguments([new Reference(EditorRequest::class), self::service($config['image_uploader']), $config['max_upload_bytes']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ReferenceSearchController::class, ReferenceSearchController::class)
            ->setArguments([new Reference(EditorRequest::class), self::service($config['mention_search']), self::service($config['link_search'])])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    private static function service(?string $id): ?Reference
    {
        return null === $id ? null : new Reference($id);
    }
}
