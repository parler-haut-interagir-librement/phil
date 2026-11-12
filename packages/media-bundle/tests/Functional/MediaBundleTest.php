<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use GdImage;
use Override;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\PhilMediaBundle;
use Phil\MediaBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

#[CoversClass(PhilMediaBundle::class)]
final class MediaBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);

        foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testAnUploadedImageIsServedAtItsSignedUrls(): void
    {
        $image = imagecreatetruecolor(200, 100);
        self::assertInstanceOf(GdImage::class, $image);
        ob_start();
        imagepng($image);
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-media-');
        file_put_contents($path, (string) ob_get_clean());

        $media = $this->service(MediaLibrary::class, MediaLibrary::class)->upload($path, 'photo.png');
        $twig = $this->service('twig', Environment::class);
        $urls = explode(' ', $twig->createTemplate("{{ media_url(media) }} {{ media_url(media, 'thumb', 'jpeg') }}")->render(['media' => $media]));
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        $original = $kernel->handle(Request::create(html_entity_decode($urls[0])));
        self::assertSame([200, 'image/png'], [$original->getStatusCode(), $original->headers->get('Content-Type')]);

        $thumb = $kernel->handle(Request::create(html_entity_decode($urls[1])));
        self::assertSame([200, 'image/jpeg'], [$thumb->getStatusCode(), $thumb->headers->get('Content-Type')]);
        self::assertSame([64, 64], \array_slice((array) getimagesizefromstring((string) $thumb->getContent()), 0, 2));

        self::assertSame(404, $kernel->handle(Request::create(explode('?', $urls[0])[0]))->getStatusCode());
    }

    public function testTheComponentsTheExplorerAndThePicker(): void
    {
        $image = imagecreatetruecolor(200, 100);
        self::assertInstanceOf(GdImage::class, $image);
        ob_start();
        imagepng($image);
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-media-');
        file_put_contents($path, (string) ob_get_clean());
        $media = $this->service(MediaLibrary::class, MediaLibrary::class)->upload($path, 'photo.png');
        $media->describe(null, 'A black rectangle');

        $twig = $this->service('twig', Environment::class);

        $img = $twig->createTemplate('<twig:PhilMedia:Img :media="media" format="thumb" sizes="64px" class="cover"/>')->render(['media' => $media]);
        self::assertMatchesRegularExpression('{^<img src="/media/'.$media->id.'/1/thumb\.webp\?expires=\d+&amp;signature=[^"]+" srcset="[^"]+ 32w, [^"]+ 64w" sizes="64px" width="64" height="64" alt="A black rectangle" loading="lazy" decoding="async" class="cover">$}', $img);

        $hero = $twig->createTemplate('<twig:PhilMedia:Img :media="media" format="thumb" priority/>')->render(['media' => $media]);
        self::assertStringEndsWith(' alt="A black rectangle" loading="eager" fetchpriority="high">', $hero);
        $heroPicture = $twig->createTemplate('<twig:PhilMedia:Picture :media="media" format="thumb" :priority="true" decoding="sync"/>')->render(['media' => $media]);
        self::assertMatchesRegularExpression('{<img [^>]+ loading="eager" decoding="sync" fetchpriority="high">\s*</picture>$}', $heroPicture);

        $picture = $twig->createTemplate('<twig:PhilMedia:Picture :media="id" format="thumb" alt=""/>')->render(['id' => $media->id]);
        self::assertStringStartsWith('<picture>', $picture);
        self::assertMatchesRegularExpression('{<source type="image/avif" srcset="/media/[^"]+thumb@32\.avif[^"]+ 32w, [^"]+ 64w">\s*<source type="image/webp"}', $picture);
        self::assertMatchesRegularExpression('{<img src="/media/[^"]+/thumb\.png\?[^"]+" srcset="[^"]+" width="64" height="64" alt="" loading="lazy" decoding="async">\s*</picture>$}', $picture);
        self::assertSame('', $twig->createTemplate('<twig:PhilMedia:Picture media="019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f"/>')->render());

        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $explorer = $kernel->handle(Request::create('/admin/media/?view=list'));
        self::assertSame(200, $explorer->getStatusCode());
        self::assertStringContainsString('photo.png', (string) $explorer->getContent());
        self::assertStringContainsString('/admin_thumbnail.webp?', (string) $explorer->getContent());

        $thumbnail = [];
        preg_match('{src="(/media/[^"]+/admin_thumbnail\.webp\?[^"]+)"}', (string) $explorer->getContent(), $thumbnail);
        self::assertSame(200, $kernel->handle(Request::create(html_entity_decode($thumbnail[1] ?? '')))->getStatusCode());

        self::assertStringContainsString('<input type="hidden" name="_csrf_token" value="csrf-token">', (string) $explorer->getContent());
        self::assertSame(403, $kernel->handle(Request::create('/admin/media/'.$media->id.'/rename', 'POST', ['filename' => 'photo-2.png'], server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']))->getStatusCode(), 'no token');
        self::assertSame(403, $kernel->handle(Request::create('/admin/media/'.$media->id.'/rename', 'POST', ['filename' => 'photo-2.png', '_csrf_token' => 'forged'], server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']))->getStatusCode(), 'a token that is none');
        self::assertSame(403, $kernel->handle(Request::create('/admin/media/'.$media->id.'/rename', 'POST', ['filename' => 'photo-2.png', '_csrf_token' => 'a-token-of-24-characters'], ['csrf-token_a-token-of-24-characters' => 'csrf-token'], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']))->getStatusCode(), 'from another site');

        $renamed = $kernel->handle(Request::create('/admin/media/'.$media->id.'/rename', 'POST', ['filename' => 'photo-2.png', '_csrf_token' => 'a-token-of-24-characters'], ['csrf-token_a-token-of-24-characters' => 'csrf-token'], server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']));
        self::assertSame([303, '/admin/media/?done=renamed'], [$renamed->getStatusCode(), $renamed->headers->get('Location')]);
        self::assertStringContainsString('csrf-token_a-token-of-24-characters=deleted', (string) $renamed->headers->get('Set-Cookie'), 'the token cookie is cleared once checked');
        self::assertStringContainsString('photo-2.png', (string) $kernel->handle(Request::create('/admin/media/?picker=1'))->getContent());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
