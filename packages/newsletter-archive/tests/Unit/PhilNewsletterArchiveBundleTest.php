<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit;

use Phil\Markup\Markup;
use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Application\Contract\WebVersionRenderer;
use Phil\NewsletterArchive\Application\IssueFeed;
use Phil\NewsletterArchive\Application\NeutralWebVersion;
use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Infrastructure\Console\ArchiveCommand;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchemaListener;
use Phil\NewsletterArchive\Infrastructure\Dbal\DbalArchiveRepository;
use Phil\NewsletterArchive\Infrastructure\DependencyInjection\ArchiveServices;
use Phil\NewsletterArchive\Infrastructure\Http\ArchiveController;
use Phil\NewsletterArchive\Infrastructure\Routing\RouterIssueUrls;
use Phil\NewsletterArchive\PhilNewsletterArchiveBundle;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\Slug\TransliteratingSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * The configuration of the bundle, its defaults and limits; the services it registers, none when it is disabled; the
 * slugger it is given; and the Flex recipe, whose configuration it accepts.
 */
#[CoversClass(PhilNewsletterArchiveBundle::class)]
#[CoversClass(ArchiveServices::class)]
final class PhilNewsletterArchiveBundleTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'connection' => 'doctrine.dbal.default_connection',
            'page_size' => 20,
            'slugger' => null,
            'slug_max_length' => 120,
        ], $this->process([]));
    }

    public function testTheLimits(): void
    {
        self::assertSame(1, $this->process(['page_size' => 1])['page_size']);
        self::assertSame(16, $this->process(['slug_max_length' => 16])['slug_max_length']);
        self::assertSame(100, $this->process(['page_size' => 100])['page_size']);
        self::assertSame(200, $this->process(['slug_max_length' => 200])['slug_max_length']);

        foreach ([['page_size' => 0], ['page_size' => 101], ['slug_max_length' => 15], ['slug_max_length' => 201], ['connection' => '']] as $config) {
            try {
                $this->process($config);
                self::fail('The configuration '.json_encode($config).' was accepted.');
            } catch (InvalidConfigurationException) {
                self::assertSame('phil_newsletter_archive', new PhilNewsletterArchiveBundle()->getContainerExtension()?->getAlias());
            }
        }
    }

    public function testTheServices(): void
    {
        $builder = $this->load([]);

        self::assertTrue($builder->getDefinition(Archive::class)->isPublic());
        self::assertTrue($builder->getDefinition(ArchiveController::class)->hasTag('controller.service_arguments'));
        self::assertSame(20, $builder->getDefinition(ArchiveController::class)->getArgument(3));
        self::assertSame(['command' => ArchiveCommand::NAME], $builder->getDefinition(ArchiveCommand::class)->getTag('console.command')[0]);
        self::assertSame(TransliteratingSlugger::class, $builder->getDefinition(ArchiveServices::SLUGGER)->getClass());
        self::assertSame([null, 120], $builder->getDefinition(ArchiveServices::SLUGGER)->getArguments());
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $builder->getDefinition(ArchiveRepository::class)->getArgument(0));
    }

    public function testEveryServiceIsWiredToItsDependencies(): void
    {
        $builder = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');
        $repository = new Reference(ArchiveRepository::class);
        $urls = new Reference(IssueUrls::class);

        self::assertSame(DbalArchiveRepository::class, $builder->getDefinition(ArchiveRepository::class)->getClass());
        self::assertEquals([$connection], $builder->getDefinition(ArchiveRepository::class)->getArguments());
        self::assertSame(NeutralWebVersion::class, $builder->getDefinition(WebVersionRenderer::class)->getClass());
        self::assertEquals([new Reference(Markup::class), new Reference(ContentRenderer::class), new Reference(TemplateRepository::class)], $builder->getDefinition(WebVersionRenderer::class)->getArguments());
        self::assertEquals([
            $repository, new Reference(CampaignRepository::class), new Reference(MailingListRepository::class),
            new Reference(WebVersionRenderer::class), new Reference(ArchiveServices::SLUGGER), new Reference('clock'),
        ], $builder->getDefinition(Archive::class)->getArguments());
        self::assertEquals([$repository], $builder->getDefinition(ArchiveReader::class)->getArguments());
        self::assertTrue($builder->getDefinition(ArchiveReader::class)->isPublic());
        self::assertSame(RouterIssueUrls::class, $builder->getDefinition(IssueUrls::class)->getClass());
        self::assertEquals([new Reference('router')], $builder->getDefinition(IssueUrls::class)->getArguments());
        self::assertEquals([$repository, $urls], $builder->getDefinition(IssueFeed::class)->getArguments());
        self::assertTrue($builder->getDefinition(IssueFeed::class)->isPublic());
        self::assertEquals([new Reference(ArchiveReader::class), new Reference(CurrentSite::class), new Reference('twig'), 20], $builder->getDefinition(ArchiveController::class)->getArguments());
        self::assertTrue($builder->getDefinition(ArchiveController::class)->isPublic());
        self::assertEquals([$connection], $builder->getDefinition(ArchiveSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema']], $builder->getDefinition(ArchiveSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(Archive::class), $urls], $builder->getDefinition(ArchiveCommand::class)->getArguments());
    }

    public function testAnotherSluggerAndConnection(): void
    {
        $builder = $this->load(['slugger' => 'app.slugger', 'connection' => 'doctrine.dbal.archive_connection', 'page_size' => 7]);

        self::assertSame('app.slugger', (string) $builder->getAlias(ArchiveServices::SLUGGER));
        self::assertEquals(new Reference('doctrine.dbal.archive_connection'), $builder->getDefinition(ArchiveRepository::class)->getArgument(0));
        self::assertSame(7, $builder->getDefinition(ArchiveController::class)->getArgument(3));
    }

    public function testADisabledBundleRegistersNothing(): void
    {
        $builder = $this->load(['enabled' => false]);

        self::assertFalse($builder->has(Archive::class));
        self::assertFalse($builder->has(ArchiveServices::SLUGGER));
    }

    public function testTheRecipe(): void
    {
        self::assertSame([
            'bundles' => [PhilNewsletterArchiveBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
        ], json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(
            ['phil_newsletter_archive' => ['resource' => '@PhilNewsletterArchiveBundle/config/routes.php', 'prefix' => '/newsletter/archive']],
            Yaml::parseFile(self::RECIPE.'/config/routes/phil_newsletter_archive.yaml'),
        );

        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_newsletter_archive.yaml');
        self::assertIsArray($config);
        self::assertIsArray($config['phil_newsletter_archive']);
        self::assertSame(20, $this->process($config['phil_newsletter_archive'])['page_size']);
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = new PhilNewsletterArchiveBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $extension = new PhilNewsletterArchiveBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config], $builder);

        return $builder;
    }
}
