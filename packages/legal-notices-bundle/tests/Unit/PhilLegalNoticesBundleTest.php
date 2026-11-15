<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use Override;
use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\Contract\ConsentCategories;
use Phil\LegalNotices\Contract\DocumentSource;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Body\MarkupBody;
use Phil\LegalNoticesBundle\Infrastructure\Consent\ConfiguredConsentCategories;
use Phil\LegalNoticesBundle\Infrastructure\Console\AnnounceCommand;
use Phil\LegalNoticesBundle\Infrastructure\Console\CheckCommand;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\LegalNoticesServices;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Phil\LegalNoticesBundle\Infrastructure\Privacy\ScheduleRetentionDurations;
use Phil\LegalNoticesBundle\Infrastructure\Sitemap\LegalUrlProvider;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Infrastructure\StructuredData\LegalPagesStructuredData;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\LegalNoticesBundle\Infrastructure\Twig\KitComponents;
use Phil\LegalNoticesBundle\Infrastructure\Twig\LegalNoticesExtension;
use Phil\LegalNoticesBundle\PhilLegalNoticesBundle;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\TwigExtraBundle\DependencyInjection\TwigExtraExtension;

#[CoversClass(PhilLegalNoticesBundle::class)]
#[CoversClass(LegalNoticesServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilLegalNoticesBundleTest extends TestCase
{
    private const array IDENTITY = [
        'publisher_name' => 'Example',
        'publisher_address' => "1 Main Street\nMontréal",
        'publication_director' => 'Jo',
        'contact_email' => 'hello@example.com',
        'host_name' => 'Host',
        'host_address' => 'Toronto',
        'host_contact' => 'host@example.com',
        'dpo_name' => 'Jo',
        'dpo_contact' => 'dpo@example.com',
    ];

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-legal-notices-bundle-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        file_put_contents($this->directory.'/notice.fr.md', "# Mentions\n\nPar \${publisher_name}.");
        file_put_contents($this->directory.'/big.md', str_repeat('a', 262145));
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->directory.'/notice.fr.md');
        unlink($this->directory.'/big.md');

        rmdir($this->directory);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_legal_notices', $this->extension()->getAlias());
        self::assertSame([
            'service_container',
            SiteIdentity::class,
            ConfigDocumentSource::class,
            MarkupBody::class,
            LegalNotices::class,
            DocumentController::class,
            FooterLinks::class,
            KitComponents::class,
            LegalNoticesExtension::class,
            LegalNoticesServices::INTL_EXTENSION,
            CheckCommand::class,
            AnnounceCommand::class,
            LegalUrlProvider::class,
            LegalPagesStructuredData::class,
        ], array_keys($container->getDefinitions()));
        self::assertSame([[]], $container->getDefinition(SiteIdentity::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ConfigDocumentSource::class)->getArguments());
        self::assertSame(ConfigDocumentSource::class, (string) $container->getAlias(DocumentSource::class));
        self::assertSame(MarkupBody::class, (string) $container->getAlias(BodyRenderer::class));
        self::assertEquals([new Definition(Markup::class, [new Definition(DefaultCompiler::class), new Definition(SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies'])])], $container->getDefinition(MarkupBody::class)->getArguments());
        self::assertFalse($container->hasAlias(RetentionDurations::class));
        self::assertFalse($container->hasAlias(ConsentCategories::class));
        self::assertEquals([
            new Reference(DocumentSource::class),
            new Reference(BodyRenderer::class),
            new Reference(SiteIdentity::class),
            new Reference(RetentionDurations::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(ConsentCategories::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            'en',
        ], $container->getDefinition(LegalNotices::class)->getArguments());
        self::assertTrue($container->getDefinition(LegalNotices::class)->isPublic());
        self::assertEquals([new Reference(LegalNotices::class), new Reference('twig'), new Reference('clock'), PhilLegalNoticesBundle::LAYOUT, true, true], $container->getDefinition(DocumentController::class)->getArguments());
        self::assertTrue($container->getDefinition(DocumentController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(DocumentController::class)->isPublic());
        self::assertEquals([new Reference(LegalNotices::class), new Reference('router'), new Reference('clock'), [], new TaggedIteratorArgument('phil_legal_notices.footer_link')], $container->getDefinition(FooterLinks::class)->getArguments());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(FooterLinks::class), new Reference(KitComponents::class)], $container->getDefinition(LegalNoticesExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(LegalNoticesExtension::class)->getTags());
        self::assertSame(IntlExtension::class, $container->getDefinition(LegalNoticesServices::INTL_EXTENSION)->getClass());
        self::assertSame(['twig.extension' => [[]]], $container->getDefinition(LegalNoticesServices::INTL_EXTENSION)->getTags());
        self::assertEquals([new Reference(LegalNotices::class)], $container->getDefinition(CheckCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(CheckCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(LegalNotices::class), new Reference('event_dispatcher'), new Reference('clock')], $container->getDefinition(AnnounceCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(AnnounceCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(LegalNotices::class), new Reference('router'), new Reference('clock'), 'default'], $container->getDefinition(LegalUrlProvider::class)->getArguments());
        self::assertSame([['after' => RouteUrlProvider::class]], $container->getDefinition(LegalUrlProvider::class)->getTag('phil_sitemap.url_provider'));
    }

    public function testTheLegalPagesAreDescribedToTheStructuredData(): void
    {
        $definition = $this->load([])->getDefinition(LegalPagesStructuredData::class);

        self::assertEquals([new Reference(LegalNotices::class), new Reference('clock'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $definition->getArguments());
        self::assertSame(['phil_structured_data.provider' => [[]]], $definition->getTags());
    }

    public function testEveryOption(): void
    {
        $container = $this->load([
            'clock' => 'app.clock',
            'default_locale' => 'fr',
            'identity' => self::IDENTITY + ['contact_phone' => null],
            'documents' => [
                'legal_notice' => [
                    ['number' => 1, 'format' => 'markdown', 'published_at' => '2026-12-01', 'bodies' => ['en-GB' => 'By ${publisher_name}.'], 'files' => ['fr-CA' => $this->directory.'/notice.fr.md'], 'summaries' => ['en-GB' => 'First']],
                    ['number' => 2, 'state' => 'draft', 'effective_at' => '2027-01-01', 'bodies' => ['en' => 'Next']],
                ],
                'privacy_policy' => [['number' => 1, 'state' => 'withdrawn', 'published_at' => '2026-12-01', 'effective_at' => '2026-12-02', 'bodies' => ['en' => 'Policy']]],
            ],
            'retention' => ['source' => 'app.schedule', 'personal_only' => false, 'labels' => ['R-1' => ['fr-CA' => 'Comptes']]],
            'consent' => ['categories' => ['necessary' => ['necessary' => true, 'labels' => ['en' => 'Necessary'], 'cookies' => ['PHPSESSID']], 'analytics' => ['labels' => ['en-GB' => 'Audience'], 'descriptions' => ['en-GB' => 'Pages']]]],
            'markup' => 'app.markup',
            'pages' => ['layout' => 'base.html.twig', 'retention_section' => false, 'consent_section' => false, 'kit_subdirectory' => 'kit'],
            'footer' => ['terms_policies' => ['terms']],
            'sitemap_section' => 'pages',
        ]);

        self::assertSame([self::IDENTITY], $container->getDefinition(SiteIdentity::class)->getArguments());
        self::assertEquals([[
            'legal_notice' => [
                ['number' => 1, 'format' => 'markdown', 'state' => 'published', 'published_at' => '2026-12-01', 'effective_at' => null, 'bodies' => ['en-GB' => 'By ${publisher_name}.', 'fr-CA' => "# Mentions\n\nPar \${publisher_name}."], 'summaries' => ['en-GB' => 'First']],
                ['number' => 2, 'format' => 'text', 'state' => 'draft', 'published_at' => null, 'effective_at' => '2027-01-01', 'bodies' => ['en' => 'Next'], 'summaries' => []],
            ],
            'privacy_policy' => [
                ['number' => 1, 'format' => 'text', 'state' => 'withdrawn', 'published_at' => '2026-12-01', 'effective_at' => '2026-12-02', 'bodies' => ['en' => 'Policy'], 'summaries' => []],
            ],
        ]], $container->getDefinition(ConfigDocumentSource::class)->getArguments());
        self::assertContainsEquals(new FileResource($this->directory.'/notice.fr.md'), $container->getResources());
        self::assertEquals([new Reference('app.markup')], $container->getDefinition(MarkupBody::class)->getArguments());
        self::assertEquals([new Reference('app.schedule'), ['R-1' => ['fr-CA' => 'Comptes']], false], $container->getDefinition(ScheduleRetentionDurations::class)->getArguments());
        self::assertSame(ScheduleRetentionDurations::class, (string) $container->getAlias(RetentionDurations::class));
        self::assertEquals([[
            'necessary' => ['necessary' => true, 'labels' => ['en' => 'Necessary'], 'cookies' => ['PHPSESSID'], 'descriptions' => []],
            'analytics' => ['labels' => ['en-GB' => 'Audience'], 'descriptions' => ['en-GB' => 'Pages'], 'necessary' => false, 'cookies' => []],
        ]], $container->getDefinition(ConfiguredConsentCategories::class)->getArguments());
        self::assertSame(ConfiguredConsentCategories::class, (string) $container->getAlias(ConsentCategories::class));
        self::assertSame('fr', $container->getDefinition(LegalNotices::class)->getArgument(5));
        self::assertEquals([new Reference(LegalNotices::class), new Reference('twig'), new Reference('app.clock'), 'base.html.twig', false, false], $container->getDefinition(DocumentController::class)->getArguments());
        self::assertEquals([new Reference(LegalNotices::class), new Reference('router'), new Reference('app.clock'), ['terms'], new TaggedIteratorArgument('phil_legal_notices.footer_link')], $container->getDefinition(FooterLinks::class)->getArguments());
        self::assertEquals([new Reference(LegalNotices::class), new Reference('event_dispatcher'), new Reference('app.clock')], $container->getDefinition(AnnounceCommand::class)->getArguments());
        self::assertSame('pages', $container->getDefinition(LegalUrlProvider::class)->getArgument(3));
        self::assertSame(['kit'], $container->getDefinition(KitComponents::class)->getArguments());
    }

    /**
     * The recipes are named after the components subdirectory of the Toolkit, unless the configuration names one; a
     * container without the bundle's services, or without the Toolkit, is left as it is.
     */
    public function testTheSubdirectoryOfTheKit(): void
    {
        $toolkit = $this->load([]);
        $toolkit->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new KitSubdirectoryPass()->process($toolkit);
        self::assertSame(['ui/kit'], $toolkit->getDefinition(KitComponents::class)->getArguments());

        $configured = $this->load(['pages' => ['kit_subdirectory' => 'mine']]);
        $configured->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($configured);
        self::assertSame(['mine'], $configured->getDefinition(KitComponents::class)->getArguments());

        $withoutToolkit = $this->load([]);
        new KitSubdirectoryPass()->process($withoutToolkit);
        self::assertSame([''], $withoutToolkit->getDefinition(KitComponents::class)->getArguments());

        $rootless = $this->load([]);
        $rootless->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($rootless);
        self::assertSame([''], $rootless->getDefinition(KitComponents::class)->getArguments());

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertSame(['service_container'], array_keys($empty->getDefinitions()));
    }

    public function testThePassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilLegalNoticesBundle()->build($container);

        self::assertContainsOnlyInstancesOf(KitSubdirectoryPass::class, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    /**
     * TwigExtraBundle registers the extension of twig/intl-extra: the bundle leaves it to it.
     */
    public function testTheIntlExtensionOfTwigExtraBundleIsKept(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtraExtension());
        $this->extension()->load([[]], $container);

        self::assertFalse($container->hasDefinition(LegalNoticesServices::INTL_EXTENSION));
    }

    public function testTheSourcesOfTheApplication(): void
    {
        $container = $this->load(['source' => 'app.documents', 'consent' => ['source' => 'app.consent', 'categories' => ['x' => ['labels' => []]]]]);

        self::assertSame('app.documents', (string) $container->getAlias(DocumentSource::class));
        self::assertFalse($container->hasDefinition(ConfigDocumentSource::class));
        self::assertSame('app.consent', (string) $container->getAlias(ConsentCategories::class));
        self::assertFalse($container->hasDefinition(ConfiguredConsentCategories::class));
    }

    public function testABundleGivesDefaultsTheApplicationOverrides(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([
            ['identity' => self::IDENTITY, 'source' => 'group.documents'],
            ['identity' => ['contact_email' => 'site@example.com']],
        ], $container);

        self::assertSame('group.documents', (string) $container->getAlias(DocumentSource::class));
        self::assertSame([array_replace(self::IDENTITY, ['contact_email' => 'site@example.com'])], $container->getDefinition(SiteIdentity::class)->getArguments());

        $container = new ContainerBuilder();
        $this->extension()->load([['source' => 'group.documents'], ['source' => 'app.documents']], $container);

        self::assertSame('app.documents', (string) $container->getAlias(DocumentSource::class));
    }

    public function testADocumentOnlyNeedsTheFieldsItRequires(): void
    {
        $container = $this->load(['identity' => ['publisher_name' => 'Example', 'contact_email' => 'a@example.com', 'dpo_name' => 'Jo', 'dpo_contact' => 'dpo@example.com'], 'documents' => [
            'privacy_policy' => [['number' => 1, 'published_at' => '2026-12-01', 'bodies' => ['en' => 'a']]],
            'legal_notice' => [['number' => 1, 'state' => 'draft', 'effective_at' => '2026-12-01', 'bodies' => ['en' => 'a']]],
        ]]);

        self::assertTrue($container->hasDefinition(ConfigDocumentSource::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'an unknown field' => [['identity' => ['owner' => 'x']], 'phil_legal_notices.identity: "owner" is not a field of the identity.'];
        yield 'both sources' => [['source' => 'app.documents', 'documents' => ['legal_notice' => [['number' => 1, 'effective_at' => '2026-12-01', 'state' => 'draft', 'bodies' => ['en' => 'a']]]]], 'phil_legal_notices: the versions come from "source" or from "documents", not from both.'];
        yield 'an unknown document' => [['documents' => ['cookies' => []]], 'phil_legal_notices.documents: "cookies" is not a document; legal_notice and privacy_policy are.'];
        yield 'a bad version' => [['documents' => ['legal_notice' => [['number' => 0, 'effective_at' => '2026-12-01', 'state' => 'draft', 'bodies' => ['en' => 'a']]]]], 'phil_legal_notices.documents: A version number goes from 1 to 999999.'];
        yield 'missing fields' => [['identity' => ['publisher_name' => 'Example'], 'documents' => ['privacy_policy' => [['number' => 1, 'published_at' => '2026-12-01', 'bodies' => ['en' => 'a']]]]], 'phil_legal_notices.identity: the privacy_policy requires contact_email, dpo_name, dpo_contact.'];
        yield 'missing fields of a withdrawn version' => [['documents' => ['legal_notice' => [['number' => 1, 'state' => 'withdrawn', 'published_at' => '2026-12-01', 'bodies' => ['en' => 'a']]]]], 'phil_legal_notices.identity: the legal_notice requires publisher_name, publisher_address, publication_director, contact_email, host_name, host_address, host_contact.'];
        yield 'a bad state' => [['documents' => ['legal_notice' => [['number' => 1, 'state' => 'gone']]]], 'gone'];
        yield 'no number' => [['documents' => ['legal_notice' => [['state' => 'draft']]]], 'number'];
        yield 'a bad consent category' => [['consent' => ['categories' => ['Bad' => ['labels' => ['en' => 'a']]]]], 'phil_legal_notices.consent: "Bad" is not a consent category key'];
        yield 'a field spelt with a dash' => [['identity' => ['publisher-name' => 'x']], '"publisher-name" is not a field of the identity.'];
        yield 'a document spelt with a dash' => [['documents' => ['legal-notice' => []]], '"legal-notice" is not a document'];
        yield 'a consent key spelt with a dash' => [['consent' => ['categories' => ['ad-tracking' => ['labels' => ['en' => 'a']]]]], '"ad-tracking" is not a consent category key'];
        yield 'an empty clock' => [['clock' => ''], 'clock'];
        yield 'an empty locale' => [['default_locale' => ''], 'default_locale'];
        yield 'an empty format' => [['documents' => ['legal_notice' => [['number' => 1, 'format' => '']]]], 'format'];
        yield 'an empty layout' => [['pages' => ['layout' => '']], 'layout'];
        yield 'an empty terms policy' => [['footer' => ['terms_policies' => ['']]], 'terms_policies'];
        yield 'an empty sitemap section' => [['sitemap_section' => ''], 'sitemap_section'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testABodyFileMustBeReadableAndSmall(): void
    {
        foreach (['/nope.md' => 'the body in "fr" is a readable file of 256 KiB at most.', '/big.md' => 'the body in "fr" is a readable file of 256 KiB at most.', '/notice.fr.md' => 'the body in "fr" is given twice, as text and as a file.'] as $file => $message) {
            try {
                $this->load(['documents' => ['legal_notice' => [['number' => 2, 'state' => 'draft', 'effective_at' => '2026-12-01', 'bodies' => '/notice.fr.md' === $file ? ['fr' => 'a'] : [], 'files' => ['fr' => $this->directory.$file]]]]]);
                self::fail('The file was accepted: '.$file);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertSame('phil_legal_notices.documents.legal_notice, version 2: '.$message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testABodyFileAtTheLimit(): void
    {
        file_put_contents($this->directory.'/limit.md', str_repeat('a', 262144));
        $container = $this->load(['documents' => ['legal_notice' => [['number' => 1, 'state' => 'draft', 'effective_at' => '2026-12-01', 'files' => ['en' => $this->directory.'/limit.md']]]]]);
        unlink($this->directory.'/limit.md');

        self::assertTrue($container->hasDefinition(ConfigDocumentSource::class));
    }

    public function testABodyFileIsAFile(): void
    {
        foreach (['%kernel.project_dir%' => $this->directory, '%list%' => ['a']] as $file => $value) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $this->directory);
            $container->setParameter('list', $value);

            try {
                $this->extension()->load([['documents' => ['legal_notice' => [['number' => 1, 'state' => 'draft', 'effective_at' => '2026-12-01', 'files' => ['en' => $file]]]]]], $container);
                self::fail('A body file that is no file was accepted.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertSame('phil_legal_notices.documents.legal_notice, version 1: the body in "en" is a readable file of 256 KiB at most.', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testAFileNamedByAParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('legal_dir', $this->directory);
        $this->extension()->load([['documents' => ['legal_notice' => [['number' => 1, 'state' => 'draft', 'effective_at' => '2026-12-01', 'files' => ['fr' => '%legal_dir%/notice.fr.md']]]]]], $container);

        self::assertEquals([['legal_notice' => [['number' => 1, 'format' => 'text', 'state' => 'draft', 'published_at' => null, 'effective_at' => '2026-12-01', 'bodies' => ['fr' => "# Mentions\n\nPar \${publisher_name}."], 'summaries' => []]]]], $container->getDefinition(ConfigDocumentSource::class)->getArguments());
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
        $extension = new PhilLegalNoticesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
