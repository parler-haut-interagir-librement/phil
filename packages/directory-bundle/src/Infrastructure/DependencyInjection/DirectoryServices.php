<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\DependencyInjection;

use Phil\DirectoryBundle\Application\Capability\AddressPipeline;
use Phil\DirectoryBundle\Application\Capability\ContactPipeline;
use Phil\DirectoryBundle\Application\Capability\EditedByPipeline;
use Phil\DirectoryBundle\Application\Capability\LocationPipeline;
use Phil\DirectoryBundle\Application\Capability\RelatedPipeline;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Application\Genre;
use Phil\DirectoryBundle\Application\Genres;
use Phil\DirectoryBundle\Application\RelatedLookup;
use Phil\DirectoryBundle\Application\Relation;
use Phil\DirectoryBundle\Application\Relations;
use Phil\DirectoryBundle\Application\UserErasure;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Infrastructure\Doctrine\AddressCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ContactCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Doctrine\DoctrineRelatedLookup;
use Phil\DirectoryBundle\Infrastructure\Doctrine\DoctrineUserErasure;
use Phil\DirectoryBundle\Infrastructure\Doctrine\EditedByCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;
use Phil\DirectoryBundle\Infrastructure\Doctrine\LocationCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Doctrine\RelatedCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Form\EditorsType;
use Phil\DirectoryBundle\Infrastructure\Form\ListingType;
use Phil\DirectoryBundle\Infrastructure\Http\DirectoryController;
use Phil\DirectoryBundle\Infrastructure\Routing\DirectoryRoutes;
use Phil\DirectoryBundle\Infrastructure\Security\Actors;
use Phil\DirectoryBundle\Infrastructure\Security\ListingVoter;
use Phil\DirectoryBundle\Infrastructure\Sitemap\DirectoryUrlProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the directory: the genres and their relations, `Directory`, the five capabilities in the pipeline and the mapping of
 * content-bundle, the actor and the voter of Symfony Security, the forms, the controller, the routes of the genres and
 * the pages of the sitemap.
 *
 * @phpstan-type DirectoryConfig array{genres: array<string, array{path: string, title: string}>, relations: array<string, array{from: string, to: string, multiple: bool, title: string, inverse_title: string}>, admin_role: string, publish_every_change: bool, page_size: int, sitemap_locale: string, allow_localhost: bool, words: array{new: string, edit: string, editors: string, history: string, publish: string}}
 */
final class DirectoryServices
{
    /** The tags content-bundle collects its pipeline and its capability mappings by. */
    public const array CONTENT_TAGS = ['phil_content.data_mapper', 'phil_content.merger', 'phil_content.normalizer'];

    public const string CAPABILITY_MAPPING_TAG = 'phil_content.capability_mapping';

    /**
     * @param DirectoryConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $genres = [];

        foreach ($config['genres'] as $key => $genre) {
            $genres[] = new Definition(Genre::class, [$key, $genre['path'], $genre['title']]);
        }

        $container->register(Genres::class, Genres::class)->setArguments([$genres]);
        $relations = [];

        foreach ($config['relations'] as $key => $relation) {
            $relations[] = new Definition(Relation::class, [$key, $relation['from'], $relation['to'], $relation['title'], $relation['inverse_title'], $relation['multiple']]);
        }

        $container->register(Relations::class, Relations::class)->setArguments([$relations]);
        $container->register(DoctrineUserErasure::class, DoctrineUserErasure::class)
            ->setArguments([new Reference('doctrine.orm.entity_manager')]);
        $container->setAlias(UserErasure::class, DoctrineUserErasure::class);
        $container->register(DoctrineRelatedLookup::class, DoctrineRelatedLookup::class)
            ->setArguments([new Reference('doctrine.orm.entity_manager')]);
        $container->setAlias(RelatedLookup::class, DoctrineRelatedLookup::class);
        $container->register(Directory::class, Directory::class)
            ->setAutowired(true)
            ->setArgument('$publishEveryChange', $config['publish_every_change'])
            ->setArgument('$relations', new Reference(Relations::class))
            ->setPublic(true);

        foreach ([ContactPipeline::class, AddressPipeline::class, LocationPipeline::class, RelatedPipeline::class, EditedByPipeline::class] as $pipeline) {
            $definition = $container->register($pipeline, $pipeline);

            foreach (self::CONTENT_TAGS as $tag) {
                $definition->addTag($tag);
            }
        }

        foreach ([ContactCapabilityMapping::class, AddressCapabilityMapping::class, LocationCapabilityMapping::class, RelatedCapabilityMapping::class, EditedByCapabilityMapping::class] as $mapping) {
            $container->register($mapping, $mapping)->addTag(self::CAPABILITY_MAPPING_TAG);
        }

        $container->register(Actors::class, Actors::class)
            ->setArguments([new Reference('security.token_storage'), new Reference('security.authorization_checker'), $config['admin_role']]);
        $container->register(ListingVoter::class, ListingVoter::class)
            ->setArguments([new Reference(Directory::class), $config['admin_role'], new Reference('security.role_hierarchy', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('security.voter');

        foreach ([ListingType::class, EditorsType::class] as $type) {
            $container->register($type, $type)->addTag('form.type');
        }

        $container->register(DirectoryController::class, DirectoryController::class)
            ->setArguments([new Reference(Directory::class), new Reference(Genres::class), new Reference(Actors::class), new Reference('form.factory'), new Reference('twig'), new Reference('router'), $config['page_size']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(DirectoryRoutes::class, DirectoryRoutes::class)
            ->setArguments([new Reference(Genres::class), $config['words']])
            ->addTag('routing.loader');
        $container->register(DirectoryUrlProvider::class, DirectoryUrlProvider::class)
            ->setArguments([new Reference(Directory::class), new Reference(Genres::class), new Reference('router'), $config['sitemap_locale'], $config['allow_localhost']])
            ->addTag('phil_sitemap.url_provider');
    }

    /**
     * What the directory adds to DoctrineBundle — the type of a listing's identifier, the mapping of its two entities —
     * and to content-bundle — the root of a listing.
     *
     * @return array{doctrine: array<string, mixed>, phil_content: array<string, mixed>}
     */
    public static function prepended(string $bundlePath): array
    {
        return [
            'doctrine' => [
                'dbal' => ['types' => [ListingIdType::NAME => ListingIdType::class]],
                'orm' => ['mappings' => ['PhilDirectory' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => $bundlePath.'/src/Domain/Model',
                    'prefix' => 'Phil\DirectoryBundle\Domain\Model',
                ]]],
            ],
            'phil_content' => ['roots' => [Listing::class]],
        ];
    }
}
