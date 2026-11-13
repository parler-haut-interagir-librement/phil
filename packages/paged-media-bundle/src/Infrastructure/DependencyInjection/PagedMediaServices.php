<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\DependencyInjection;

use Phil\FootnotesSidenotes\NoteResolver;
use Phil\PagedMedia\Gotenberg\BasicAuth;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\FileCertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\InlineCertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Signing\VaultCertificateSource;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\DocumentTemplates;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PagedMediaQueue;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;
use Phil\PagedMediaBundle\Application\SignatureDefaults;
use Phil\PagedMediaBundle\Infrastructure\Messenger\FailedJobPurger;
use Phil\PagedMediaBundle\Infrastructure\Messenger\PagedMediaHandler;
use Phil\PagedMediaBundle\Infrastructure\Notes\PrintedNotes;
use Phil\PagedMediaBundle\Infrastructure\Storage\CacheJobStore;
use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryJobStore;
use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec;
use Phil\PagedMediaBundle\Infrastructure\Template\PrintedTemplates;
use Phil\Ujo\Html\HtmlRenderer;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Event\SyncMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The services of the configuration: `PagedMedia`, the one service; the library's printed templates as document
 * templates, unless `templates.enabled` is false; `PrintedNotes` with `phil/footnotes-sidenotes`; the Gotenberg client
 * when a URL is configured; the certificate source and the timestamp authority of the signatures; the storage of the
 * files rendered by messages and the store of their jobs, in a directory or in a cache pool; and, when Messenger is installed, `PagedMediaQueue`, the handler of the three messages and,
 * unless `jobs.remove_failed` is false, the listener that forgets the job of a message that failed for good.
 */
final class PagedMediaServices
{
    public const string TEMPLATE_TAG = 'phil_paged_media.template';

    /**
     * @param array{url: ?string, username: ?string, password: ?string, timeout: ?float, http_client: string}                                                                                                                                                                                                                                         $gotenberg
     * @param array{certificate: array{type: string, path: ?string, key_path: ?string, password: ?string, certificate: ?string, key: ?string, vault_address: ?string, vault_token: ?string, vault_path: ?string, vault_mount: string}, timestamp_url: ?string, reason: ?string, location: ?string, contact_info: ?string, name: ?string, pades: bool} $signing
     * @param array{directory: string}                                                                                                                                                                                                                                                                                                                $storage
     * @param array{directory: string, remove_failed: bool, cache_pool: ?string, lifetime: ?int}                                                                                                                                                                                                                                                      $jobs
     * @param array{enabled: bool, font: ?string, bold_font: ?string}                                                                                                                                                                                                                                                                                 $templates
     */
    public static function register(ContainerBuilder $container, array $gotenberg, array $signing, array $storage, array $jobs, array $templates = ['enabled' => true, 'font' => null, 'bold_font' => null]): void
    {
        $container->register(DocumentTemplates::class, DocumentTemplates::class)
            ->setArguments([new TaggedIteratorArgument(self::TEMPLATE_TAG, 'name')]);

        if ($templates['enabled']) {
            $fonts = new Definition(TemplateFonts::class)->setFactory([PrintedTemplates::class, 'fonts'])->setArguments([$templates['font'], $templates['bold_font']]);
            $container->setDefinition('phil_paged_media.template_fonts', $fonts);

            foreach (PrintedTemplates::TEMPLATES as $name => $template) {
                $container->register($template, $template)
                    ->setArguments([new Reference('phil_paged_media.template_fonts')])
                    ->addTag(self::TEMPLATE_TAG, ['name' => $name]);
            }
        }

        if (null !== $gotenberg['url']) {
            $container->register(Gotenberg::class, Gotenberg::class)
                ->setArguments([
                    new Reference($gotenberg['http_client']),
                    $gotenberg['url'],
                    null === $gotenberg['username'] ? null : $container->register('phil_paged_media.basic_auth', BasicAuth::class)->setArguments([$gotenberg['username'], $gotenberg['password'] ?? '']),
                    $gotenberg['timeout'],
                ])
                ->setPublic(true);
        }

        self::registerSigning($container, $signing, $gotenberg['http_client']);

        $container->register(PagedMedia::class, PagedMedia::class)
            ->setArguments([
                new Reference(DocumentTemplates::class),
                new Reference(Gotenberg::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(CertificateSource::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(TimestampAuthority::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $container->register('phil_paged_media.signature_defaults', SignatureDefaults::class)->setArguments([$signing['reason'], $signing['location'], $signing['contact_info'], $signing['name'], $signing['pades']]),
                new Reference(PrintEngine::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        if (class_exists(NoteResolver::class)) {
            $container->register(NoteResolver::class, NoteResolver::class);
            $container->register(PrintedNotes::class, PrintedNotes::class)
                ->setArguments([new Reference(NoteResolver::class), new Definition(HtmlRenderer::class)->setFactory([HtmlRenderer::class, 'default'])])
                ->setPublic(true);
        }

        $container->register(DirectoryStorage::class, DirectoryStorage::class)->setArguments([$storage['directory']]);
        $container->setAlias(PdfStorage::class, DirectoryStorage::class)->setPublic(true);
        $container->register(JobCodec::class, JobCodec::class);
        $container->register(DirectoryJobStore::class, DirectoryJobStore::class)->setArguments([$jobs['directory'], new Reference(JobCodec::class)]);

        if (null === $jobs['cache_pool']) {
            $container->setAlias(JobStore::class, DirectoryJobStore::class)->setPublic(true);
        } else {
            $container->register(CacheJobStore::class, CacheJobStore::class)->setArguments([new Reference($jobs['cache_pool']), new Reference(JobCodec::class), $jobs['lifetime']]);
            $container->setAlias(JobStore::class, CacheJobStore::class)->setPublic(true);
        }

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(PagedMediaQueue::class, PagedMediaQueue::class)
                ->setArguments([new Reference(MessageBusInterface::class), new Reference(JobStore::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->setPublic(true);
            $container->register(PagedMediaHandler::class, PagedMediaHandler::class)
                ->setArguments([new Reference(PagedMedia::class), new Reference(PdfStorage::class), new Reference(JobStore::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('messenger.message_handler', ['handles' => RenderPdf::class, 'method' => 'render'])
                ->addTag('messenger.message_handler', ['handles' => RunPipeline::class, 'method' => 'run'])
                ->addTag('messenger.message_handler', ['handles' => ConvertDocuments::class, 'method' => 'convert']);

            if ($jobs['remove_failed']) {
                $container->register(FailedJobPurger::class, FailedJobPurger::class)
                    ->setArguments([new Reference(JobStore::class)])
                    ->addTag('kernel.event_listener', ['event' => WorkerMessageFailedEvent::class, 'method' => 'onWorkerMessageFailed'])
                    ->addTag('kernel.event_listener', ['event' => SyncMessageFailedEvent::class, 'method' => 'onSyncMessageFailed']);
            }
        }
    }

    /**
     * @param array{certificate: array{type: string, path: ?string, key_path: ?string, password: ?string, certificate: ?string, key: ?string, vault_address: ?string, vault_token: ?string, vault_path: ?string, vault_mount: string}, timestamp_url: ?string, reason: ?string, location: ?string, contact_info: ?string, name: ?string, pades: bool} $signing
     */
    private static function registerSigning(ContainerBuilder $container, array $signing, string $httpClient): void
    {
        $certificate = $signing['certificate'];

        match ($certificate['type']) {
            'file' => $container->register(CertificateSource::class, FileCertificateSource::class)->setArguments([$certificate['path'], $certificate['key_path'], $certificate['password']]),
            'inline' => $container->register(CertificateSource::class, InlineCertificateSource::class)->setArguments([$certificate['certificate'], $certificate['key'], $certificate['password']]),
            'vault' => $container->register(CertificateSource::class, VaultCertificateSource::class)->setArguments([new Reference($httpClient), $certificate['vault_address'], $certificate['vault_token'], $certificate['vault_path'], $certificate['vault_mount']]),
            default => null,
        };

        if (null !== $signing['timestamp_url']) {
            $container->register(TimestampAuthority::class, TimestampAuthorityUrl::class)->setArguments([$signing['timestamp_url']]);
        }
    }
}
