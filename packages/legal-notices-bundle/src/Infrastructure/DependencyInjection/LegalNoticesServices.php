<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\DependencyInjection;

use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\Contract\ConsentCategories;
use Phil\LegalNotices\Contract\DocumentSource;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\Field;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Body\MarkupBody;
use Phil\LegalNoticesBundle\Infrastructure\Consent\ConfiguredConsentCategories;
use Phil\LegalNoticesBundle\Infrastructure\Console\AnnounceCommand;
use Phil\LegalNoticesBundle\Infrastructure\Console\CheckCommand;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Phil\LegalNoticesBundle\Infrastructure\Privacy\ScheduleRetentionDurations;
use Phil\LegalNoticesBundle\Infrastructure\Sitemap\LegalUrlProvider;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Infrastructure\StructuredData\LegalPagesStructuredData;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\LegalNoticesBundle\Infrastructure\Twig\KitComponents;
use Phil\LegalNoticesBundle\Infrastructure\Twig\LegalNoticesExtension;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\Sitemap\UrlProvider;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extra\Intl\IntlExtension;

/**
 * The services of the configuration. The identity, the versions written in the configuration and the consent
 * categories are checked here, and so are the fields each published document requires, so that a mistake fails the
 * container build rather than the first request; an option that needs a package which is not installed fails it too.
 *
 * @phpstan-type Version array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, files: array<string, string>, summaries: array<string, string>}
 * @phpstan-type Config array{
 *     clock: string,
 *     default_locale: string,
 *     identity: array<string, string|null>,
 *     source: string|null,
 *     documents: array<string, list<Version>>,
 *     retention: array{source: string|null, personal_only: bool, labels: array<string, array<string, string>>},
 *     consent: array{source: string|null, categories: array<string, array{necessary: bool, labels: array<string, string>, descriptions: array<string, string>, cookies: list<string>}>},
 *     markup: string|null,
 *     pages: array{layout: string, retention_section: bool, consent_section: bool, kit_subdirectory: string|null},
 *     footer: array{terms_policies: list<string>},
 *     sitemap_section: string,
 * }
 */
final class LegalNoticesServices
{
    public const string FOOTER_LINK_TAG = 'phil_legal_notices.footer_link';

    public const string SITEMAP_TAG = 'phil_sitemap.url_provider';

    public const string STRUCTURED_DATA_TAG = 'phil_structured_data.provider';

    public const string SITEMAP_ROUTES = RouteUrlProvider::class;

    public const string INTL_EXTENSION = 'phil_legal_notices.twig.intl_extension';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference($config['clock']);
        $identity = self::identity($config['identity']);

        $container->register(SiteIdentity::class, SiteIdentity::class)->setArguments([$identity->values()]);
        self::source($container, $config, $identity);
        self::body($container, $config['markup']);
        self::retention($container, $config['retention']);
        self::consent($container, $config['consent']);

        $container->register(LegalNotices::class, LegalNotices::class)
            ->setArguments([
                new Reference(DocumentSource::class),
                new Reference(BodyRenderer::class),
                new Reference(SiteIdentity::class),
                new Reference(RetentionDurations::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ConsentCategories::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['default_locale'],
            ])
            ->setPublic(true);
        $notices = new Reference(LegalNotices::class);

        $container->register(DocumentController::class, DocumentController::class)
            ->setArguments([$notices, new Reference('twig'), $clock, $config['pages']['layout'], $config['pages']['retention_section'], $config['pages']['consent_section']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(FooterLinks::class, FooterLinks::class)->setArguments([$notices, new Reference('router'), $clock, $config['footer']['terms_policies'], new TaggedIteratorArgument(self::FOOTER_LINK_TAG)]);
        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['pages']['kit_subdirectory']]);
        $container->register(LegalNoticesExtension::class, LegalNoticesExtension::class)
            ->setArguments([new Reference(FooterLinks::class), new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        // format_list, which writes the lists of the pages in their language; TwigExtraBundle registers it when present.
        if (!$container->hasExtension('twig_extra')) {
            $container->register(self::INTL_EXTENSION, IntlExtension::class)->addTag('twig.extension');
        }

        $container->register(CheckCommand::class, CheckCommand::class)->setArguments([$notices])->addTag('console.command');
        $container->register(AnnounceCommand::class, AnnounceCommand::class)->setArguments([$notices, new Reference('event_dispatcher'), $clock])->addTag('console.command');

        if (interface_exists(UrlProvider::class)) {
            $container->register(LegalUrlProvider::class, LegalUrlProvider::class)
                ->setArguments([$notices, new Reference('router'), $clock, $config['sitemap_section']])
                ->addTag(self::SITEMAP_TAG, ['after' => self::SITEMAP_ROUTES]);
        }

        if (interface_exists(StructuredDataProvider::class)) {
            $container->register(LegalPagesStructuredData::class, LegalPagesStructuredData::class)
                ->setArguments([$notices, $clock, new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag(self::STRUCTURED_DATA_TAG);
        }
    }

    /**
     * @param array<string, string|null> $values
     */
    private static function identity(array $values): SiteIdentity
    {
        try {
            return new SiteIdentity($values);
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            throw new InvalidConfigurationException('phil_legal_notices.identity: '.$invalidLegalNotice->getMessage(), $invalidLegalNotice->getCode(), previous: $invalidLegalNotice);
        }
    }

    /**
     * @param Config $config
     */
    private static function source(ContainerBuilder $container, array $config, SiteIdentity $identity): void
    {
        if (null !== $config['source']) {
            if ([] !== $config['documents']) {
                throw new InvalidConfigurationException('phil_legal_notices: the versions come from "source" or from "documents", not from both.');
            }

            $container->setAlias(DocumentSource::class, $config['source']);

            return;
        }

        $documents = [];

        foreach ($config['documents'] as $type => $versions) {
            $type = DocumentType::tryFrom($type) ?? throw new InvalidConfigurationException(\sprintf('phil_legal_notices.documents: "%s" is not a document; legal_notice and privacy_policy are.', $type));
            $documents[$type->value] = array_map(static fn (array $version): array => self::version($container, $type, $version), $versions);
        }

        try {
            $source = new ConfigDocumentSource($documents);
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            throw new InvalidConfigurationException('phil_legal_notices.documents: '.$invalidLegalNotice->getMessage(), $invalidLegalNotice->getCode(), previous: $invalidLegalNotice);
        }

        foreach (DocumentType::cases() as $type) {
            $missing = [] === $source->history($type)->publicVersions() ? [] : $identity->missing($type);

            if ([] !== $missing) {
                throw new InvalidConfigurationException(\sprintf('phil_legal_notices.identity: the %s requires %s.', $type->value, implode(', ', array_map(static fn (Field $field): string => $field->value, $missing))));
            }
        }

        $container->register(ConfigDocumentSource::class, ConfigDocumentSource::class)->setArguments([$documents]);
        $container->setAlias(DocumentSource::class, ConfigDocumentSource::class);
    }

    /**
     * The version with the body of each file read into its bodies.
     *
     * @param Version $version
     *
     * @return array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, summaries: array<string, string>}
     */
    private static function version(ContainerBuilder $container, DocumentType $type, array $version): array
    {
        $bodies = $version['bodies'];

        foreach ($version['files'] as $locale => $file) {
            $path = $container->getParameterBag()->resolveValue($file);
            $where = \sprintf('phil_legal_notices.documents.%s, version %d', $type->value, $version['number']);

            if (isset($bodies[$locale])) {
                throw new InvalidConfigurationException(\sprintf('%s: the body in "%s" is given twice, as text and as a file.', $where, $locale));
            }

            if (!\is_string($path) || !is_file($path) || !is_readable($path) || filesize($path) > LegalDocumentVersion::MAX_BODY_BYTES) {
                throw new InvalidConfigurationException(\sprintf('%s: the body in "%s" is a readable file of 256 KiB at most.', $where, $locale));
            }

            $container->addResource(new FileResource($path));
            $bodies[$locale] = (string) file_get_contents($path);
        }

        unset($version['files']);
        $version['bodies'] = $bodies;

        return $version;
    }

    private static function body(ContainerBuilder $container, ?string $markup): void
    {
        if (null !== $markup) {
            $container->register(MarkupBody::class, MarkupBody::class)->setArguments([new Reference($markup)]);
        } elseif (class_exists(Markup::class)) {
            $purifier = new Definition(SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
            $container->register(MarkupBody::class, MarkupBody::class)->setArguments([new Definition(Markup::class, [new Definition(DefaultCompiler::class), $purifier])]);
        } else {
            $container->register(PlainTextBody::class, PlainTextBody::class);
            $container->setAlias(BodyRenderer::class, PlainTextBody::class);

            return;
        }

        $container->setAlias(BodyRenderer::class, MarkupBody::class);
    }

    /**
     * @param array{source: string|null, personal_only: bool, labels: array<string, array<string, string>>} $retention
     */
    private static function retention(ContainerBuilder $container, array $retention): void
    {
        if (null === $retention['source']) {
            return;
        }

        if (!interface_exists(RetentionScheduleSource::class)) {
            throw new InvalidConfigurationException('phil_legal_notices.retention.source needs phil/privacy-bundle.');
        }

        $container->register(ScheduleRetentionDurations::class, ScheduleRetentionDurations::class)
            ->setArguments([new Reference($retention['source']), $retention['labels'], $retention['personal_only']]);
        $container->setAlias(RetentionDurations::class, ScheduleRetentionDurations::class);
    }

    /**
     * @param array{source: string|null, categories: array<string, array{necessary: bool, labels: array<string, string>, descriptions: array<string, string>, cookies: list<string>}>} $consent
     */
    private static function consent(ContainerBuilder $container, array $consent): void
    {
        if (null !== $consent['source']) {
            $container->setAlias(ConsentCategories::class, $consent['source']);

            return;
        }

        if ([] === $consent['categories']) {
            return;
        }

        try {
            new ConfiguredConsentCategories($consent['categories']);
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            throw new InvalidConfigurationException('phil_legal_notices.consent: '.$invalidLegalNotice->getMessage(), $invalidLegalNotice->getCode(), previous: $invalidLegalNotice);
        }

        $container->register(ConfiguredConsentCategories::class, ConfiguredConsentCategories::class)->setArguments([$consent['categories']]);
        $container->setAlias(ConsentCategories::class, ConfiguredConsentCategories::class);
    }
}
