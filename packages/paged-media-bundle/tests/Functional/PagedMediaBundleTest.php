<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Functional;

use Override;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMediaBundle\Application\DocumentTemplates;
use Phil\PagedMediaBundle\Application\Event\PdfStored;
use Phil\PagedMediaBundle\Application\Exception\NotConfigured;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PagedMediaQueue;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;
use Phil\PagedMediaBundle\Infrastructure\Messenger\FailedJobPurger;
use Phil\PagedMediaBundle\Infrastructure\Storage\CacheJobStore;
use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryJobStore;
use Phil\PagedMediaBundle\Tests\Functional\App\GotenbergResponses;
use Phil\PagedMediaBundle\Tests\Functional\App\StoredFiles;
use Phil\PagedMediaBundle\Tests\Functional\App\TestKernel;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[CoversNothing]
final class PagedMediaBundleTest extends TestCase
{
    private ?TestKernel $kernel = null;

    private string $storage;

    private string $jobs;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir().'/phil-paged-media-bundle-storage-'.getmypid();
        $this->jobs = sys_get_temp_dir().'/phil-paged-media-bundle-jobs-'.getmypid();

        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        new Filesystem()->remove([$this->storage, $this->jobs]);
    }

    public function testWithoutConfigurationOnlyTheNativeWriterIsThere(): void
    {
        $container = $this->boot([]);
        $media = $this->service($container, PagedMedia::class, PagedMedia::class);

        self::assertStringStartsWith('%PDF-1.7', $media->renderTemplate('ticket')->contents);
        self::assertFalse($container->has(Gotenberg::class));
        self::assertInstanceOf(PdfStorage::class, $this->service($container, PdfStorage::class, PdfStorage::class));
        self::assertInstanceOf(JobStore::class, $this->service($container, JobStore::class, JobStore::class));

        $this->expectException(NotConfigured::class);
        $media->gotenberg();
    }

    public function testGotenbergAndTheSigningPortsComeFromTheConfiguration(): void
    {
        $container = $this->boot([
            'gotenberg' => ['url' => 'http://gotenberg:3000', 'username' => 'app', 'password' => 'secret', 'timeout' => 30],
            'signing' => [
                'certificate' => ['type' => 'vault', 'vault_address' => 'http://vault:8200', 'vault_token' => 't', 'vault_path' => 'pdf/signer'],
                'timestamp_url' => 'https://tsa.example.com',
                'reason' => 'Approval',
                'pades' => true,
            ],
        ]);
        $media = $this->service($container, PagedMedia::class, PagedMedia::class);
        $responses = $this->service($container, GotenbergResponses::class, GotenbergResponses::class);

        self::assertSame('9.1.0', $media->gotenberg()->version());
        self::assertSame(['certificate' => 'signer.pem', 'key' => 'signer.key', 'reason' => 'Approval', 'pades' => true, 'timestampUrl' => 'https://tsa.example.com'], $media->signature()->options());
        self::assertSame('%PDF-gotenberg', $media->process(Files::of(InputFile::fromContents('a.pdf', '%PDF')), new Pipeline(new Optimize()))->contents);
        self::assertSame('http://gotenberg:3000/version', $responses->requests[0][1]);
        self::assertSame('http://vault:8200/v1/secret/data/pdf/signer', $responses->requests[1][1]);
        $headers = $responses->requests[0][2]['headers'] ?? [];
        self::assertIsArray($headers);
        self::assertContains('Authorization: Basic '.base64_encode('app:secret'), $headers);
    }

    public function testMessagesRenderRunAndKeepTheFiles(): void
    {
        $container = $this->boot([
            'gotenberg' => ['url' => 'http://gotenberg:3000'],
            'signing' => ['certificate' => ['type' => 'inline', 'certificate' => 'C', 'key' => 'K']],
            'storage' => ['directory' => $this->storage],
            'jobs' => ['directory' => $this->jobs],
        ]);
        $queue = $this->service($container, PagedMediaQueue::class, PagedMediaQueue::class);
        $stored = $this->service($container, StoredFiles::class, StoredFiles::class);

        $ticket = $queue->render('ticket', ['seat' => 'A1'], 'tickets/a.pdf', filename: 'a.pdf');
        $signed = $queue->run(Files::of(InputFile::fromContents('b.pdf', '%PDF')), new Pipeline(new Optimize()), 'signed/b.pdf', true);

        self::assertStringStartsWith('%PDF-1.7', (string) file_get_contents($this->storage.'/tickets/a.pdf'));
        self::assertSame('%PDF-gotenberg', file_get_contents($this->storage.'/signed/b.pdf'));
        self::assertEquals([new PdfStored($ticket, 'tickets/a.pdf', 'a.pdf', 'application/pdf'), new PdfStored($signed, 'signed/b.pdf', 'out.pdf', 'application/pdf')], $stored->events);
        self::assertSame([], glob($this->jobs.'/*.json'));
    }

    public function testThePrintedTemplatesAreRegisteredAndRenderedFromAJobInACachePool(): void
    {
        $container = $this->boot(['storage' => ['directory' => $this->storage], 'jobs' => ['cache_pool' => 'paged_media.jobs', 'lifetime' => 600]]);
        $queue = $this->service($container, PagedMediaQueue::class, PagedMediaQueue::class);
        $templates = $this->service($container, DocumentTemplates::class, DocumentTemplates::class);
        $pool = $this->service($container, 'paged_media.jobs', CacheItemPoolInterface::class);
        $bus = new RecordingBus();

        self::assertInstanceOf(CacheJobStore::class, $container->get(JobStore::class));
        self::assertSame(['paged_media.address_labels', 'paged_media.calendar', 'paged_media.download_cards', 'paged_media.gift_cards', 'paged_media.large_print', 'paged_media.name_badges', 'paged_media.product_labels', 'paged_media.shelf_labels', 'ticket'], $this->sorted($templates->names()));

        $parameters = ['card_title' => 'Gift', 'cards' => [['amount' => '$50', 'code' => 'GC-SECRET-CODE', 'recipient' => 'Ada Lovelace']]];
        $id = new PagedMediaQueue($bus, $this->service($container, JobStore::class, JobStore::class))->render('paged_media.gift_cards', $parameters, 'cards/a.pdf');
        $message = serialize($bus->messages[0]);

        self::assertLessThan(120, \strlen($message));
        self::assertStringNotContainsString('SECRET', $message);
        self::assertStringNotContainsString('Ada', $message);
        self::assertTrue($pool->hasItem($id));

        $queue->render('paged_media.gift_cards', $parameters, 'cards/b.pdf');

        self::assertStringStartsWith('%PDF-1.7', (string) file_get_contents($this->storage.'/cards/b.pdf'));
    }

    public function testThePrintedTemplatesCanBeLeftOut(): void
    {
        $container = $this->boot(['templates' => ['enabled' => false]]);

        self::assertSame(['ticket'], $this->service($container, DocumentTemplates::class, DocumentTemplates::class)->names());
        self::assertInstanceOf(DirectoryJobStore::class, $container->get(JobStore::class));
    }

    public function testTheJobOfAMessageFailedForGoodIsForgottenUnlessKept(): void
    {
        $container = $this->boot(['jobs' => ['directory' => $this->jobs]]);
        $jobs = $this->service($container, JobStore::class, JobStore::class);
        $events = $this->service($container, 'event_dispatcher', EventDispatcherInterface::class);
        $job = $jobs->keep(new RenderJob('ticket', [], 'a.pdf'));

        $events->dispatch(new WorkerMessageFailedEvent(new Envelope(new RenderPdf($job)), 'async', new RuntimeException('Failed.')));
        self::assertSame([], glob($this->jobs.'/*.json'));
        $this->kernel?->shutdown();

        $container = $this->boot(['jobs' => ['directory' => $this->jobs, 'remove_failed' => false]]);
        $jobs = $this->service($container, JobStore::class, JobStore::class);
        $job = $jobs->keep(new RenderJob('ticket', [], 'a.pdf'));
        $this->service($container, 'event_dispatcher', EventDispatcherInterface::class)->dispatch(new WorkerMessageFailedEvent(new Envelope(new RenderPdf($job)), 'async', new RuntimeException('Failed.')));

        self::assertFalse($container->has(FailedJobPurger::class));
        self::assertSame([$this->jobs.'/'.$job.'.json'], glob($this->jobs.'/*.json'), 'Kept, for a retry from a failure transport.');
    }

    public function testACertificateOnDiskAndATimestampAuthorityAreWired(): void
    {
        $container = $this->boot(['signing' => ['certificate' => ['type' => 'file', 'path' => __FILE__, 'key_path' => __DIR__.'/App/Ticket.php'], 'timestamp_url' => 'http://tsa.example.com']]);

        $source = $this->service($container, CertificateSource::class, CertificateSource::class);
        $authority = $this->service($container, TimestampAuthority::class, TimestampAuthority::class);
        $media = $this->service($container, PagedMedia::class, PagedMedia::class);

        self::assertSame(basename(__FILE__), $source->certificate()->certificate->name);
        self::assertSame('Ticket.php', $source->certificate()->key?->name);
        self::assertSame('http://tsa.example.com', $authority->url());
        self::assertSame(['timestampUrl' => 'http://tsa.example.com'], $media->timestamp()->options());
    }

    public function testACertificateWithoutWhatItNeedsIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('A file certificate needs its path, an inline one its certificate, a vault one its address, token and path');

        $this->boot(['signing' => ['certificate' => ['type' => 'vault', 'vault_address' => 'http://vault:8200']]]);
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config): ContainerInterface
    {
        $this->kernel = new TestKernel($config);
        $this->kernel->boot();

        return $this->service($this->kernel->getContainer(), 'test.service_container', ContainerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerInterface $container, string $id, string $class): object
    {
        $service = $container->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
