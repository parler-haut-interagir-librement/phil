<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Field;
use Phil\LegalNotices\Placeholders;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\LegalNoticesServices;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\PhilLegalNoticesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, mounts its routes and writes a configuration whose two documents are
 * drafts, with bodies in English and French that name only fields of the identity; once the identity is filled in and
 * the versions published, the same configuration builds.
 */
#[CoversClass(PhilLegalNoticesBundle::class)]
#[CoversClass(LegalNoticesServices::class)]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheManifestRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilLegalNoticesBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/', 'legal/' => 'legal/'],
        ], $manifest);
        self::assertFileExists(self::RECIPE.'/post-install.txt');
    }

    public function testTheRoutesAreTheBundlesUnderAPrefixPerLocale(): void
    {
        self::assertSame(
            ['phil_legal_notices' => ['resource' => '@PhilLegalNoticesBundle/config/routes.php', 'prefix' => ['en' => '', 'fr' => '/fr']]],
            Yaml::parseFile(self::RECIPE.'/config/routes/phil_legal_notices.yaml'),
        );
        self::assertFileExists(__DIR__.'/../../config/routes.php');
    }

    public function testTheDocumentsStartAsDraftsWithABodyInEachLanguage(): void
    {
        $documents = $this->load($this->config())->get(ConfigDocumentSource::class);
        self::assertInstanceOf(ConfigDocumentSource::class, $documents);
        $fields = array_map(static fn (Field $field): string => $field->value, Field::cases());

        foreach (DocumentType::cases() as $type) {
            $history = $documents->history($type);
            self::assertSame([], $history->publicVersions(), $type->value);
            self::assertCount(1, $history->versions, $type->value);
            $version = $history->versions[0];
            self::assertSame(['en', 'fr'], array_keys($version->bodies), $type->value);

            foreach ($version->bodies as $locale => $body) {
                $names = Placeholders::names($body);
                self::assertNotSame([], $names, $type->value.' '.$locale);
                self::assertSame([], array_diff($names, $fields), $type->value.' '.$locale.' names only fields of the identity.');
                self::assertSame([], array_diff(array_map(static fn (Field $field): string => $field->value, $type->requiredFields()), $names), $type->value.' '.$locale.' names every field the document requires.');
            }
        }
    }

    public function testTheDocumentsArePublishedOnceTheIdentityIsFilledIn(): void
    {
        $config = $this->config();

        foreach (array_keys($config['documents']) as $type) {
            $config['documents'][$type][0]['state'] = 'published';
            $config['documents'][$type][0]['published_at'] = '2026-12-01';
        }

        try {
            $this->load($config);
            self::fail('A published document needs the fields of its identity.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith('phil_legal_notices.identity: the legal_notice requires publisher_name, publisher_address', $invalidConfigurationException->getMessage());
        }

        $config['identity'] = array_map(static fn (): string => 'Filled in', $config['identity']);
        $config['identity']['contact_email'] = 'hello@example.com';
        $documents = $this->load($config)->get(ConfigDocumentSource::class);
        self::assertInstanceOf(ConfigDocumentSource::class, $documents);

        foreach (DocumentType::cases() as $type) {
            self::assertCount(1, $documents->history($type)->publicVersions(), $type->value);
        }
    }

    /**
     * @return array{identity: array<string, string|null>, documents: array<string, list<array<string, mixed>>>}&array<string, mixed>
     */
    private function config(): array
    {
        $yaml = Yaml::parseFile(self::RECIPE.'/config/packages/phil_legal_notices.yaml');
        self::assertIsArray($yaml);
        self::assertSame(['phil_legal_notices'], array_keys($yaml));
        $config = $yaml['phil_legal_notices'];
        self::assertIsArray($config);
        self::assertIsArray($config['identity']);
        self::assertSame(array_map(static fn (Field $field): string => $field->value, [Field::SiteName, Field::PublisherName, Field::PublisherAddress, Field::PublicationDirector, Field::ContactEmail, Field::HostName, Field::HostAddress, Field::HostContact, Field::DpoName, Field::DpoContact]), array_keys($config['identity']));
        self::assertSame([null], array_values(array_unique($config['identity'], \SORT_REGULAR)));
        self::assertIsArray($config['documents']);

        /** @var array{identity: array<string, string|null>, documents: array<string, list<array<string, mixed>>>}&array<string, mixed> $config */
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::RECIPE);

        $extension = new PhilLegalNoticesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config], $container);

        return $container;
    }
}
