<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\PhilMediaBundle;
use Phil\MediaBundle\Tests\Functional\App\TestKernel;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * The bundle beside phil/browser-security-bundle: its files served under the policy of user content, its images with
 * their placeholders, its SVG images purified.
 */
#[CoversClass(PhilMediaBundle::class)]
final class UserContentTest extends KernelTestCase
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

        if (!class_exists(PhilBrowserSecurityBundle::class)) {
            self::markTestSkipped('phil/browser-security-bundle is not installed.');
        }

        self::bootKernel(['environment' => 'user_content']);
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testFilesAreServedAsUserContent(): void
    {
        $library = self::getContainer()->get(MediaLibrary::class);
        self::assertInstanceOf(MediaLibrary::class, $library);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        $photo = $library->upload(Images::file(Images::png(200, 100)), 'photo.png');
        $logo = $library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>'), 'logo.svg');
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', $library->original($logo));

        $urls = explode(' ', $twig->createTemplate('{{ media_url(photo) }} {{ media_url(logo) }}')->render(['photo' => $photo, 'logo' => $logo]));
        $original = $kernel->handle(Request::create(html_entity_decode($urls[0])));
        self::assertSame([200, 'image/png', 'inline; filename=photo.png', 'same-origin', 'no-referrer'], [$original->getStatusCode(), $original->headers->get('Content-Type'), $original->headers->get('Content-Disposition'), $original->headers->get('Cross-Origin-Resource-Policy'), $original->headers->get('Referrer-Policy')]);
        self::assertSame("default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox", $original->headers->get('Content-Security-Policy'));
        $vector = $kernel->handle(Request::create(html_entity_decode($urls[1])));
        self::assertSame(['image/svg+xml', 'attachment; filename=logo.svg', 'nosniff'], [$vector->headers->get('Content-Type'), $vector->headers->get('Content-Disposition'), $vector->headers->get('X-Content-Type-Options')]);
        self::assertSame($original->headers->get('Content-Security-Policy'), $vector->headers->get('Content-Security-Policy'));
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', $vector->getContent());

        self::assertStringStartsWith('data:image/webp;base64,', (string) $photo->placeholder());
        $img = $twig->createTemplate('<twig:PhilMedia:Img :media="media" format="thumb"/>')->render(['media' => $photo]);
        self::assertStringNotContainsString('style=', $img);
        self::assertStringContainsString(' data-controller="phil--media-bundle--placeholder" data-phil--media-bundle--placeholder-src-value="data:image/webp;base64,', $img);
        self::assertStringEndsWith('" data-phil--media-bundle--placeholder-position-value="50% 50%">', $img);
        $styled = $twig->createTemplate('<twig:PhilMedia:Picture :media="media" format="thumb" data-controller="zoom" data-zoom-scale-value="2"/>')->render(['media' => $photo]);
        self::assertStringContainsString(' data-controller="zoom phil--media-bundle--placeholder" data-phil--media-bundle--placeholder-src-value="', $styled);
        self::assertStringEndsWith('" data-phil--media-bundle--placeholder-position-value="50% 50%" data-zoom-scale-value="2">'."\n</picture>", $styled);
        $plain = $twig->createTemplate('<twig:PhilMedia:Img :media="media" format="thumb" :placeholder="false" data-controller="zoom"/>')->render(['media' => $photo]);
        self::assertStringNotContainsString('placeholder', $plain);
        self::assertStringEndsWith(' decoding="async" data-controller="zoom">', $plain);
    }
}
