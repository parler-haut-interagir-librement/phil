<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit;

use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\ContentBundle\PhilContentBundle;
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
use Phil\DirectoryBundle\Domain\Capability\AddressCapable;
use Phil\DirectoryBundle\Domain\Capability\ContactCapable;
use Phil\DirectoryBundle\Domain\Capability\EditedByCapable;
use Phil\DirectoryBundle\Domain\Capability\LocationCapable;
use Phil\DirectoryBundle\Domain\Capability\RelatedCapable;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\DirectoryBundle\Infrastructure\DependencyInjection\DirectoryServices;
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
use Phil\DirectoryBundle\PhilDirectoryBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PhilDirectoryBundle::class)]
#[CoversClass(DirectoryServices::class)]
#[CoversClass(DirectoryRoutes::class)]
#[CoversClass(ListingVoter::class)]
#[CoversClass(Actors::class)]
#[CoversClass(ContactCapabilityMapping::class)]
#[CoversClass(AddressCapabilityMapping::class)]
#[CoversClass(LocationCapabilityMapping::class)]
#[CoversClass(EditedByCapabilityMapping::class)]
#[CoversClass(ListingIdType::class)]
final class InfrastructureTest extends TestCase
{
    public function testTheConfigurationAndTheServices(): void
    {
        $container = $this->load(['genres' => ['podcast' => ['path' => 'balados', 'title' => 'Balados']]]);

        self::assertSame('phil_directory', $this->extension()->getAlias());
        $genres = $container->getDefinition(Genres::class)->getArgument(0);
        self::assertIsArray($genres);
        self::assertInstanceOf(Definition::class, $genres[0]);
        self::assertSame([Genre::class, ['podcast', 'balados', 'Balados']], [$genres[0]->getClass(), $genres[0]->getArguments()]);
        self::assertTrue($container->getDefinition(Directory::class)->isAutowired());
        self::assertTrue($container->getDefinition(Directory::class)->isPublic());
        self::assertFalse($container->getDefinition(Directory::class)->getArgument('$publishEveryChange'));
        self::assertEquals(new Reference(Relations::class), $container->getDefinition(Directory::class)->getArgument('$relations'));
        self::assertSame([], $container->getDefinition(Relations::class)->getArgument(0));
        self::assertTrue($container->getDefinition(RelatedPipeline::class)->hasTag('phil_content.merger'));
        self::assertTrue($container->getDefinition(RelatedCapabilityMapping::class)->hasTag('phil_content.capability_mapping'));

        $related = $this->load([
            'genres' => ['larp' => ['path' => 'gn', 'title' => 'GN'], 'larp_site' => ['path' => 'terrains', 'title' => 'Terrains']],
            'relations' => ['played_at' => ['from' => 'larp', 'to' => 'larp_site', 'title' => 'Joué à', 'inverse_title' => 'GN joués ici'], 'one' => ['from' => 'larp', 'to' => 'larp', 'multiple' => false, 'title' => 'a', 'inverse_title' => 'b']],
        ]);
        $relations = $related->getDefinition(Relations::class)->getArgument(0);
        self::assertIsArray($relations);
        self::assertInstanceOf(Definition::class, $relations[0]);
        self::assertInstanceOf(Definition::class, $relations[1]);
        self::assertSame([Relation::class, ['played_at', 'larp', 'larp_site', 'Joué à', 'GN joués ici', true]], [$relations[0]->getClass(), $relations[0]->getArguments()]);
        self::assertFalse($relations[1]->getArgument(5));

        foreach ([ContactPipeline::class, AddressPipeline::class, LocationPipeline::class, EditedByPipeline::class] as $pipeline) {
            self::assertSame(['phil_content.data_mapper', 'phil_content.merger', 'phil_content.normalizer'], array_keys($container->getDefinition($pipeline)->getTags()));
        }

        foreach ([ContactCapabilityMapping::class, AddressCapabilityMapping::class, LocationCapabilityMapping::class, EditedByCapabilityMapping::class] as $mapping) {
            self::assertTrue($container->getDefinition($mapping)->hasTag('phil_content.capability_mapping'));
        }

        self::assertSame([PhilContentBundle::DATA_MAPPER_TAG, PhilContentBundle::MERGER_TAG, PhilContentBundle::NORMALIZER_TAG], DirectoryServices::CONTENT_TAGS);
        self::assertSame(PhilContentBundle::CAPABILITY_MAPPING_TAG, DirectoryServices::CAPABILITY_MAPPING_TAG);
        self::assertEquals([new Reference('security.token_storage'), new Reference('security.authorization_checker'), 'ROLE_ADMIN'], $container->getDefinition(Actors::class)->getArguments());
        self::assertEquals([new Reference(Directory::class), 'ROLE_ADMIN', new Reference('security.role_hierarchy', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ListingVoter::class)->getArguments());
        self::assertTrue($container->getDefinition(ListingVoter::class)->hasTag('security.voter'));
        self::assertTrue($container->getDefinition(ListingType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(EditorsType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(Directory::class), new Reference(Genres::class), new Reference(Actors::class), new Reference('form.factory'), new Reference('twig'), new Reference('router'), 50], $container->getDefinition(DirectoryController::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineUserErasure::class)->getArguments());
        self::assertSame(DoctrineUserErasure::class, (string) $container->getAlias(UserErasure::class));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineRelatedLookup::class)->getArguments());
        self::assertSame(DoctrineRelatedLookup::class, (string) $container->getAlias(RelatedLookup::class));
        self::assertTrue($container->getDefinition(DirectoryController::class)->isPublic());
        self::assertTrue($container->getDefinition(DirectoryController::class)->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(Genres::class), ['new' => 'new', 'edit' => 'edit', 'editors' => 'editors', 'history' => 'history', 'publish' => 'publish']], $container->getDefinition(DirectoryRoutes::class)->getArguments());
        self::assertTrue($container->getDefinition(DirectoryRoutes::class)->hasTag('routing.loader'));
        self::assertEquals([new Reference(Directory::class), new Reference(Genres::class), new Reference('router'), '%kernel.default_locale%', false], $container->getDefinition(DirectoryUrlProvider::class)->getArguments());
        self::assertTrue($container->getDefinition(DirectoryUrlProvider::class)->hasTag('phil_sitemap.url_provider'));

        $other = $this->load(['admin_role' => 'ROLE_EDITOR', 'publish_every_change' => true, 'page_size' => 20, 'sitemap_locale' => 'en', 'allow_localhost' => true, 'words' => ['new' => 'ajouter']]);
        self::assertSame(20, $other->getDefinition(DirectoryController::class)->getArgument(6));
        self::assertTrue($other->getDefinition(Directory::class)->getArgument('$publishEveryChange'));
        self::assertSame('ROLE_EDITOR', $other->getDefinition(Actors::class)->getArgument(2));
        self::assertSame('ROLE_EDITOR', $other->getDefinition(ListingVoter::class)->getArgument(1));
        self::assertSame(['en', true], [$other->getDefinition(DirectoryUrlProvider::class)->getArgument(3), $other->getDefinition(DirectoryUrlProvider::class)->getArgument(4)]);
        $words = $other->getDefinition(DirectoryRoutes::class)->getArgument(1);
        self::assertIsArray($words);
        self::assertSame('ajouter', $words['new']);
        self::assertSame([], $other->getDefinition(Genres::class)->getArgument(0));

        foreach ([1, 500] as $pageSize) {
            self::assertSame($pageSize, $this->load(['page_size' => $pageSize])->getDefinition(DirectoryController::class)->getArgument(6), 'the bounds are allowed');
        }
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['genres' => ['podcast' => ['title' => 'x']]], ['genres' => ['podcast' => ['path' => '', 'title' => 'x']]], ['admin_role' => ''], ['words' => ['edit' => '']], ['page_size' => 0], ['page_size' => 501]] as $config) {
            try {
                $this->load($config);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testDoctrineAndContentAreTold(): void
    {
        $container = new ContainerBuilder();

        foreach (['doctrine', 'phil_content'] as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        $bundle = new PhilDirectoryBundle();

        self::assertSame([['roots' => [Listing::class]]], $container->getExtensionConfig('phil_content'));
        self::assertSame([[
            'dbal' => ['types' => ['phil_directory_listing_id' => ListingIdType::class]],
            'orm' => ['mappings' => ['PhilDirectory' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => $bundle->getPath().'/src/Domain/Model', 'prefix' => 'Phil\DirectoryBundle\Domain\Model']]],
        ]], $container->getExtensionConfig('doctrine'));
        self::assertDirectoryExists($bundle->getPath().'/src/Domain/Model');

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([[], []], [$alone->getExtensionConfig('doctrine'), $alone->getExtensionConfig('phil_content')]);
    }

    public function testTheRoutesOfEachGenre(): void
    {
        $routes = new DirectoryRoutes(new Genres([new Genre('podcast', 'balados', 'Balados'), new Genre('artist', 'artistes', 'Artistes')]), ['new' => 'ajouter', 'edit' => 'modifier', 'editors' => 'redacteurs', 'history' => 'historique', 'publish' => 'publier']);

        self::assertTrue($routes->supports('.', 'phil_directory'));
        self::assertFalse($routes->supports('.', 'annotation'));
        self::assertFalse($routes->supports('.'));
        $collection = $routes->load('.', 'phil_directory');
        self::assertCount(14, $collection);
        $paths = [];

        foreach ($collection->all() as $name => $route) {
            if (str_starts_with($name, 'phil_directory_podcast_')) {
                $paths[substr($name, 23)] = [$route->getPath(), $route->getMethods(), $route->getDefault('_controller'), $route->getDefault('genre'), $route->getRequirements()];
            }
        }

        $uuid = ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'];
        self::assertSame([
            'index' => ['/balados', ['GET'], [DirectoryController::class, 'index'], 'podcast', []],
            'new' => ['/balados/ajouter', ['GET', 'POST'], [DirectoryController::class, 'new'], 'podcast', []],
            'show' => ['/balados/{slug}', ['GET'], [DirectoryController::class, 'show'], 'podcast', ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*']],
            'edit' => ['/balados/{id}/modifier', ['GET', 'POST'], [DirectoryController::class, 'edit'], 'podcast', $uuid],
            'editors' => ['/balados/{id}/redacteurs', ['GET', 'POST'], [DirectoryController::class, 'editors'], 'podcast', $uuid],
            'history' => ['/balados/{id}/historique', ['GET'], [DirectoryController::class, 'history'], 'podcast', $uuid],
            'publish' => ['/balados/{id}/publier', ['POST'], [DirectoryController::class, 'publish'], 'podcast', $uuid],
        ], $paths);
        self::assertSame(['phil_directory_artist_show', 'phil_directory'], [DirectoryRoutes::name('artist', 'show'), DirectoryRoutes::TYPE]);
        self::assertSame(array_keys($collection->all())[0], 'phil_directory_podcast_index');
        self::assertSame($routes, $routes->getResolver()->resolve('.', 'phil_directory'));
        $resolver = new LoaderResolver();
        $routes->setResolver($resolver);
        self::assertSame($resolver, $routes->getResolver());
    }

    public function testTheActorAndTheVoter(): void
    {
        $tokens = new TokenStorage();
        $authorization = new AuthorizationChecker($tokens, new AccessDecisionManager([new RoleVoter()]));
        $actors = new Actors($tokens, $authorization, 'ROLE_ADMIN');

        self::assertSame([null, false], [$actors->current()->user, $actors->current()->admin]);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null, ['ROLE_USER']), 'main', ['ROLE_USER']));
        self::assertSame(['ada', false], [$actors->current()->user, $actors->current()->admin]);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('root', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']));
        self::assertSame(['root', true], [$actors->current()->user, $actors->current()->admin]);

        $listing = new Listing();
        $listing->assign('podcast', 'ada');
        $listing->addEditor('grace');

        $directory = new ReflectionClass(Directory::class)->newInstanceWithoutConstructor();
        $voter = new ListingVoter($directory, 'ROLE_ADMIN');
        $hierarchy = new ListingVoter($directory, 'ROLE_ADMIN', new RoleHierarchy(['ROLE_SUPER' => ['ROLE_ADMIN']]));
        $token = static fn (string $user, string $role): UsernamePasswordToken => new UsernamePasswordToken(new InMemoryUser($user, null, [$role]), 'main', [$role]);

        $votes = [];

        foreach ([['ada', 'ROLE_USER'], ['grace', 'ROLE_USER'], ['bob', 'ROLE_USER'], ['root', 'ROLE_ADMIN'], ['super', 'ROLE_SUPER']] as [$user, $role]) {
            foreach ([ListingVoter::EDIT, ListingVoter::EDITORS, ListingVoter::PUBLISH] as $attribute) {
                $votes[$user][] = $voter->vote($token($user, $role), $listing, [$attribute]);
            }

            $votes[$user][] = $hierarchy->vote($token($user, $role), $listing, [ListingVoter::PUBLISH]);
        }

        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;
        self::assertSame([
            'ada' => [$granted, $granted, $denied, $denied],
            'grace' => [$granted, $denied, $denied, $denied],
            'bob' => [$denied, $denied, $denied, $denied],
            'root' => [$granted, $granted, $granted, $granted],
            'super' => [$denied, $denied, $denied, $granted],
        ], $votes);
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token('ada', 'ROLE_USER'), $listing, ['other']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new stdClass(), [ListingVoter::EDIT]));
        self::assertSame(['phil_directory.edit', 'phil_directory.editors', 'phil_directory.publish'], [ListingVoter::EDIT, ListingVoter::EDITORS, ListingVoter::PUBLISH]);
    }

    public function testARelationJoinsDeclaredGenres(): void
    {
        $genres = ['larp' => ['path' => 'gn', 'title' => 'GN']];

        foreach ([
            ['from' => 'larp', 'to' => 'nope', 'title' => 'a', 'inverse_title' => 'b'],
            ['from' => 'nope', 'to' => 'larp', 'title' => 'a', 'inverse_title' => 'b'],
            ['from' => 'larp', 'to' => 'larp', 'inverse_title' => 'b'],
            ['from' => 'larp', 'to' => 'larp', 'title' => 'a'],
        ] as $relation) {
            try {
                $this->load(['genres' => $genres, 'relations' => ['r' => $relation]]);
                self::fail(json_encode($relation, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringNotContainsString('larp,', $invalid->getMessage());
            }
        }

        try {
            $this->load(['relations' => ['r' => ['from' => 'larp', 'to' => 'site', 'title' => 'a', 'inverse_title' => 'b'], 's' => ['from' => 'larp', 'to' => 'other', 'title' => 'a', 'inverse_title' => 'b']]]);
            self::fail('Genres that are not declared.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('A relation joins genres of the directory; larp, site, other is not one.', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheMappingsAndTheIdentifierType(): void
    {
        $fields = static fn (CapabilityMapping $mapping): mixed => new ReflectionMethod($mapping, 'fields')->invoke($mapping);

        self::assertSame([['fieldName' => 'contact', 'type' => 'phil_contact', 'columnName' => 'contact', 'nullable' => true]], $fields(new ContactCapabilityMapping()));
        self::assertSame([['fieldName' => 'address', 'type' => 'phil_address', 'columnName' => 'address', 'nullable' => true]], $fields(new AddressCapabilityMapping()));
        self::assertSame([
            ['fieldName' => 'latitude', 'type' => 'float', 'columnName' => 'location_latitude', 'nullable' => true],
            ['fieldName' => 'longitude', 'type' => 'float', 'columnName' => 'location_longitude', 'nullable' => true],
        ], $fields(new LocationCapabilityMapping()));
        self::assertSame([['fieldName' => 'related', 'type' => 'json', 'columnName' => 'related', 'nullable' => true]], $fields(new RelatedCapabilityMapping()));
        self::assertSame(RelatedCapable::class, new RelatedCapabilityMapping()->capability());
        self::assertSame([['fieldName' => 'editedBy', 'type' => 'string', 'columnName' => 'edited_by', 'length' => 255, 'nullable' => true]], $fields(new EditedByCapabilityMapping()));
        self::assertSame([
            ContactCapable::class,
            AddressCapable::class,
            LocationCapable::class,
            EditedByCapable::class,
        ], [new ContactCapabilityMapping()->capability(), new AddressCapabilityMapping()->capability(), new LocationCapabilityMapping()->capability(), new EditedByCapabilityMapping()->capability()]);
        self::assertSame('phil_directory_listing_id', ListingIdType::NAME);
        self::assertSame(ListingId::class, new ReflectionMethod(ListingIdType::class, 'identifierClass')->invoke(new ListingIdType()));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilDirectoryBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
