<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Profiler\MediaDataCollector;
use Phil\MediaBundle\Infrastructure\Profiler\MediaReport;
use Phil\MediaBundle\Infrastructure\Profiler\MediaTrace;
use Phil\MediaBundle\Infrastructure\Profiler\TraceableImageProcessor;
use Phil\MediaBundle\Infrastructure\Profiler\TraceableMediaStorage;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

#[CoversClass(MediaTrace::class)]
#[CoversClass(MediaReport::class)]
#[CoversClass(TraceableMediaStorage::class)]
#[CoversClass(TraceableImageProcessor::class)]
#[CoversClass(MediaDataCollector::class)]
final class ProfilerTest extends TestCase
{
    public function testTheCollectorShowsWhatARequestRenderedAndRead(): void
    {
        $trace = new MediaTrace();
        $storage = new TraceableMediaStorage(new FilesystemStorage(Images::directory()), $trace);
        $library = new MediaLibrary(new DoctrineMediaRepository(Database::entityManager()), $storage, new TraceableImageProcessor(new GdImageProcessor(), $trace), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC')), ['thumb' => ImageFormat::of('thumb', 10, 10)]);
        $media = $library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $library->rendered($media, 'thumb', 'png');
        $library->rendered($media, 'thumb', 'png');

        $storage->deletePrefix('formats/none');
        $storage->write('formats/gone/a.png', 'x');
        $storage->deletePrefix('formats/gone');
        self::assertNull($storage->read('formats/gone/a.png'));

        $collector = new MediaDataCollector($trace);
        self::assertSame([[], [], 0.0, 0], [$collector->storage(), $collector->renders(), $collector->renderTime(), $collector->hits()]);
        $collector->collect(new Request(), new Response());

        $key = \sprintf('formats/%s/1/thumb-50-50.png', $media->id);
        $rendered = $library->rendered($media, 'thumb', 'png');
        $original = \strlen(Images::png(40, 20));
        self::assertSame([
            ['operation' => 'write', 'key' => $media->storageKey(), 'bytes' => $original, 'found' => true],
            ['operation' => 'read', 'key' => $key, 'bytes' => 0, 'found' => false],
            ['operation' => 'read', 'key' => $media->storageKey(), 'bytes' => $original, 'found' => true],
            ['operation' => 'write', 'key' => $key, 'bytes' => \strlen($rendered), 'found' => true],
            ['operation' => 'read', 'key' => $key, 'bytes' => \strlen($rendered), 'found' => true],
            ['operation' => 'delete', 'key' => 'formats/none', 'bytes' => 0, 'found' => true],
            ['operation' => 'write', 'key' => 'formats/gone/a.png', 'bytes' => 1, 'found' => true],
            ['operation' => 'delete', 'key' => 'formats/gone', 'bytes' => 0, 'found' => true],
            ['operation' => 'read', 'key' => 'formats/gone/a.png', 'bytes' => 0, 'found' => false],
        ], $collector->storage());
        self::assertCount(1, $collector->renders());
        self::assertSame(['thumb', 'png', \strlen($rendered)], [$collector->renders()[0]['format'], $collector->renders()[0]['type'], $collector->renders()[0]['bytes']]);
        self::assertGreaterThan(0.0, $collector->renders()[0]['milliseconds']);
        self::assertLessThan(60_000.0, $collector->renders()[0]['milliseconds']);
        self::assertSame($collector->renders()[0]['milliseconds'], $collector->renderTime());
        self::assertSame(1, $collector->hits());
        self::assertSame(['phil_media', '@PhilMedia/profiler/collector.html.twig'], [$collector->getName(), MediaDataCollector::TEMPLATE]);

        $collector->reset();
        self::assertSame([[], [], [], []], [$collector->storage(), $collector->renders(), $trace->storage(), $trace->renders()]);
    }

    public function testTheHitsAreRenderingsFoundInTheStorage(): void
    {
        $trace = new MediaTrace();
        $trace->stored('read', 'formats/a/1/x.png', 3);
        $trace->stored('read', 'formats/a/1/y.png', 0, false);
        $trace->stored('read', 'originals/a/1/a.png', 3);
        $trace->stored('write', 'formats/a/1/z.png', 3);
        $trace->rendered('a', 'webp', 1, 1.5);
        $trace->rendered('b', 'avif', 1, 2.0);

        $collector = new MediaDataCollector($trace);
        $collector->collect(new Request(), new Response());

        self::assertSame([1, 3.5], [$collector->hits(), $collector->renderTime()]);
    }

    public function testThePanelRenders(): void
    {
        $trace = new MediaTrace();
        $trace->stored('read', 'formats/a/1/x.png', 3);
        $trace->stored('write', 'formats/a/1/z.png', 3);
        $trace->rendered('thumb', 'webp', 120, 1.25);

        $collector = new MediaDataCollector($trace);
        $collector->collect(new Request(), new Response());

        $filesystem = new FilesystemLoader();
        $filesystem->addPath(\dirname(__DIR__, 3).'/templates', 'PhilMedia');

        $twig = new Environment(new ChainLoader([new ArrayLoader([
            '@WebProfiler/Profiler/layout.html.twig' => '{% block toolbar %}{% endblock %}|{% block menu %}{% endblock %}|{% block panel %}{% endblock %}',
            '@WebProfiler/Profiler/toolbar_item.html.twig' => '{{ icon }}{{ text }}',
        ]), $filesystem]), ['strict_variables' => true]);

        $html = $twig->render(MediaDataCollector::TEMPLATE, ['collector' => $collector]);
        self::assertStringContainsString('<b>Rendered</b> <span>1 in 1.3 ms</span>', $html);
        self::assertStringContainsString('<b>Read back</b> <span>1</span>', $html);
        self::assertStringContainsString('<tr><td>thumb</td><td>webp</td><td>120</td><td>1.3</td></tr>', $html);
        self::assertStringContainsString('<tr><td>read</td><td><code>formats/a/1/x.png</code></td><td>3</td><td>yes</td></tr>', $html);
        self::assertStringContainsString('<tr><td>write</td><td><code>formats/a/1/z.png</code></td><td>3</td><td>—</td></tr>', $html);
        self::assertStringContainsString('<span class="label">', $html);

        $collector->reset();
        $empty = $twig->render(MediaDataCollector::TEMPLATE, ['collector' => $collector]);
        self::assertStringContainsString('No image was rendered during this request.', $empty);
        self::assertStringContainsString('The storage was not used during this request.', $empty);
        self::assertStringContainsString('<span class="label disabled">', $empty);
        self::assertStringNotContainsString('renderings', $empty);
    }
}
