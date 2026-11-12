<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\MediaBundle\Tests\Unit\Fixtures\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(MediaPickerType::class)]
final class MediaPickerTypeTest extends TestCase
{
    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    private FormFactoryInterface $forms;

    private Environment $twig;

    protected function setUp(): void
    {
        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $clock = new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC'));
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), $clock, ['admin_thumbnail' => ImageFormat::of('admin_thumbnail', 240, 240, 'fit')]);
        $locator = new FileLocator(\dirname(__DIR__, 3).'/config');
        $routes = new PhpFileLoader($locator)->load('routes.php');
        $routes->addPrefix('/media');

        $admin = new PhpFileLoader($locator)->load('admin_routes.php');
        $admin->addPrefix('/admin/media');

        $routes->addCollection($admin);
        $urls = new UrlGenerator($routes, new RequestContext());
        $this->forms = Forms::createFormFactoryBuilder()->addType(new MediaPickerType($this->repository, $urls))->getFormFactory();
        $bridge = new ReflectionClass(FormExtension::class)->getFileName();
        self::assertIsString($bridge);
        $this->twig = Twig::environment($urls, new MediaExtension($urls, new UrlSigner('secret'), $clock), \dirname($bridge, 2).'/Resources/views/Form');
        $engine = new TwigRendererEngine(['form_div_layout.html.twig', '@PhilMedia/form/media_picker.html.twig'], $this->twig);
        $this->twig->addExtension(new FormExtension());
        $this->twig->addExtension(new TranslationExtension());
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]));
    }

    public function testTheFieldHoldsAMediumIdentifier(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png');
        $form = $this->forms->createBuilder(FormType::class)->add('cover', MediaPickerType::class, ['collection' => 'covers'])->getForm();
        $form->submit(['cover' => $media->id]);

        self::assertTrue($form->isValid());
        $cover = $form->get('cover')->getData();
        self::assertInstanceOf(MediaId::class, $cover);
        self::assertSame($media->id, $cover->value);

        $html = $this->twig->createTemplate('{{ form_widget(form.cover) }}')->render(['form' => $form->createView()]);
        self::assertStringContainsString('data-phil--media-bundle--picker-url-value="/admin/media/?picker=1&amp;collection=covers"', $html);
        self::assertStringContainsString('value="'.$media->id.'" data-phil--media-bundle--picker-target="input"', $html);
        self::assertStringContainsString('fête.png', $html);
        self::assertStringContainsString('/admin_thumbnail.webp?signature=', $html);
        self::assertStringNotContainsString('No file chosen', $html);
    }

    public function testAnEmptyOrWrongValue(): void
    {
        $form = $this->forms->createBuilder(FormType::class)->add('cover', MediaPickerType::class)->getForm();
        $form->submit(['cover' => '']);
        self::assertNull($form->get('cover')->getData());

        $html = $this->twig->createTemplate('{{ form_widget(form.cover) }}')->render(['form' => $form->createView()]);
        self::assertStringContainsString('data-phil--media-bundle--picker-url-value="/admin/media/?picker=1"', $html);
        self::assertStringContainsString('No file chosen', $html);

        $wrong = $this->forms->createBuilder(FormType::class)->add('cover', MediaPickerType::class)->getForm();
        $wrong->submit(['cover' => 'not-a-medium']);
        self::assertFalse($wrong->get('cover')->isSynchronized());
        self::assertSame(['This is not a medium.', 'A medium is identified by a lower-case UUID; "not-a-medium" is not one.'], [$wrong->get('cover')->getTransformationFailure()?->getInvalidMessage(), $wrong->get('cover')->getTransformationFailure()?->getPrevious()?->getMessage()]);
        self::assertSame('This is not a medium.', $wrong->get('cover')->getConfig()->getOption('invalid_message'));

        $disabled = $this->forms->createBuilder(FormType::class)->add('cover', MediaPickerType::class, ['disabled' => true])->getForm();
        self::assertSame(2, substr_count($this->twig->createTemplate('{{ form_widget(form.cover) }}')->render(['form' => $disabled->createView()]), ' disabled>'));
    }

    public function testTheDataIsShownAsItsIdentifier(): void
    {
        $media = $this->library->upload(Images::file('%PDF-1.4'), 'rapport.pdf');

        foreach ([MediaId::fromString($media->id), $media] as $data) {
            $form = $this->forms->create(MediaPickerType::class, $data);
            self::assertSame($media->id, $form->getViewData());
            self::assertSame($media, $form->createView()->vars['media']);
        }

        self::assertSame('', $this->forms->create(MediaPickerType::class)->getViewData());
        $type = new MediaPickerType($this->repository, new UrlGenerator(new RouteCollection(), new RequestContext()));
        self::assertSame(['phil_media_picker', HiddenType::class], [$type->getBlockPrefix(), $type->getParent()]);
        self::assertNull($this->forms->create(MediaPickerType::class)->createView()->vars['media']);

        $this->expectException(InvalidOptionsException::class);
        $this->forms->create(MediaPickerType::class, null, ['collection' => 3]);
    }
}
