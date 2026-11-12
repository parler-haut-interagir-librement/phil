<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaAdmin;
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaField;
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaFieldConfigurator;
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\MediaBundle\Tests\Unit\Fixtures\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(MediaField::class)]
#[CoversClass(MediaFieldConfigurator::class)]
#[CoversClass(MediaAdmin::class)]
final class EasyAdminTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager(records: true);
        $this->repository = new DoctrineMediaRepository($this->entityManager);
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC')), ['admin_thumbnail' => ImageFormat::of('admin_thumbnail', 240, 240, 'fit')]);
    }

    public function testTheFieldShowsAMediumAndOpensThePicker(): void
    {
        $field = MediaField::new('cover', 'Cover')->setCollection('covers')->getAsDto();

        self::assertSame(['cover', 'Cover', '@PhilMedia/easyadmin/field.html.twig', MediaPickerType::class, 'covers'], [$field->getProperty(), $field->getLabel(), $field->getTemplatePath(), $field->getFormType(), $field->getCustomOption(MediaField::OPTION_COLLECTION)]);
        self::assertStringContainsString('field-phil-media', $field->getCssClass());
        self::assertNull(MediaField::new('cover')->getAsDto()->getCustomOption('phil_media_collection'));
        self::assertSame(['phil_media_collection', '@PhilMedia/easyadmin/field.html.twig'], [MediaField::OPTION_COLLECTION, MediaField::TEMPLATE]);
    }

    public function testTheConfiguratorFindsTheMedium(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png');
        $configurator = new MediaFieldConfigurator($this->repository);
        $metadata = $this->entityManager->getClassMetadata(Post::class);
        $entity = new EntityDto($metadata->getName(), $metadata);
        $context = new ReflectionClass(AdminContext::class)->newInstanceWithoutConstructor();

        $text = TextField::new('title')->getAsDto();
        $text->setFieldFqcn(TextField::class);
        self::assertTrue($configurator->supports($this->field(null, null), $entity));
        self::assertFalse($configurator->supports($text, $entity));

        foreach ([MediaId::fromString($media->id), $media, $media->id] as $value) {
            $field = $this->field($value, 'covers');
            $configurator->configure($field, $entity, $context);
            self::assertSame([$media, 'covers'], [$field->getFormattedValue(), $field->getFormTypeOption('collection')]);
        }

        foreach ([null, '019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f', 'not-a-medium', 42, new stdClass()] as $value) {
            $field = $this->field($value, null);
            $configurator->configure($field, $entity, $context);
            self::assertNull($field->getFormattedValue());
            self::assertNull($field->getFormTypeOption('collection'));
        }

        $chosen = $this->field($media->id, 'covers');
        $chosen->setFormTypeOption('collection', 'kept');

        $configurator->configure($chosen, $entity, $context);
        self::assertSame('kept', $chosen->getFormTypeOption('collection'));
    }

    public function testTheTemplateShowsTheMediumOrNone(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png');
        $locator = new FileLocator(\dirname(__DIR__, 3).'/config');
        $routes = new PhpFileLoader($locator)->load('routes.php');
        $routes->addPrefix('/media');

        $urls = new UrlGenerator($routes, new RequestContext());
        $twig = Twig::environment($urls, new MediaExtension($urls, new UrlSigner('secret'), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC'))));

        $field = MediaField::new('cover')->getAsDto();
        $field->setFormattedValue($media);

        $html = $twig->render(MediaField::TEMPLATE, ['field' => $field]);
        self::assertStringContainsString('<span class="phil-media-field"><img class="phil-media-thumb" src="/media/'.$media->id.'/1/admin_thumbnail.webp?signature=', $html);
        self::assertStringContainsString('fête.png', $html);

        $field->setFormattedValue(null);
        self::assertSame('<span class="badge badge-secondary">None</span>', trim($twig->render(MediaField::TEMPLATE, ['field' => $field])));
    }

    private function field(mixed $value, ?string $collection): FieldDto
    {
        $field = MediaField::new('cover')->setCollection($collection)->getAsDto();
        $field->setFieldFqcn(MediaField::class);
        $field->setValue($value);

        return $field;
    }

    public function testTheMenuLinksToTheExplorer(): void
    {
        $item = MediaAdmin::menuItem()->getAsDto();
        self::assertSame(['Media', 'fa fa-photo-film', 'phil_media_admin', []], [$item->getLabel(), $item->getIcon(), $item->getRouteName(), $item->getRouteParameters()]);
        self::assertSame('phil_media_admin', MediaAdminController::INDEX_ROUTE);

        $label = new class implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return $translator->trans('menu.covers', locale: $locale);
            }
        };
        $covers = MediaAdmin::menuItem($label, null, 'covers')->getAsDto();
        self::assertSame([$label, null, ['collection' => 'covers']], [$covers->getLabel(), $covers->getIcon(), $covers->getRouteParameters()]);
    }
}
