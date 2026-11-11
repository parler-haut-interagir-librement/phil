<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\PhilUrlBundle;
use Phil\UrlBundle\Tests\Functional\App\Entity\Shop;
use Phil\UrlBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

#[CoversClass(PhilUrlBundle::class)]
final class UrlBundleTest extends KernelTestCase
{
    private string $locale;

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

        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Locale::setDefault($this->locale);
    }

    public function testAnAddressIsStoredInItsColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Shop(Url::fromString('HTTPS://Example.com')));
        $entityManager->flush();
        $entityManager->clear();

        $shop = $entityManager->getRepository(Shop::class)->findOneBy([]);
        self::assertSame('https://example.com/', $shop?->website->toString());
    }

    public function testTheConstraintSpeaksTheLocale(): void
    {
        $validator = $this->service('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        self::assertCount(0, $validator->validate(new Shop(Url::fromString('https://example.com'), 'https://instagram.com/shop')));
        $violations = $validator->validate(new Shop(Url::fromString('https://example.com'), 'https://example.com/'));
        self::assertSame('Cette adresse n’est d’aucun des genres acceptés : Facebook, Instagram.', $violations->get(0)->getMessage());
        self::assertSame('Cette valeur n’est pas une adresse web valide.', $validator->validate(new Shop(Url::fromString('https://example.com'), 'nope'))->get(0)->getMessage());
    }

    public function testTheFieldAndTwigAreReady(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('social', TypedUrlType::class, null, ['types' => [KnownUrlType::Facebook]]);
        $form->submit('example.com');

        self::assertFalse($form->isValid());
        self::assertSame(['Cette adresse n’est d’aucun des genres acceptés.'], array_map(static fn (FormError|FormErrorIterator $error): string => $error->getMessage(), iterator_to_array($form->getErrors())));

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertSame('YouTube youtube.com/@shop', $twig->createTemplate('{{ url_type(link)|url_type_label }} {{ link|url_display }}')->render(['link' => 'https://www.youtube.com/@shop/']));
        self::assertSame('Facebook', $twig->createTemplate('{{ url_type(link)|url_type_label }}')->render(['link' => TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/x')]));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
