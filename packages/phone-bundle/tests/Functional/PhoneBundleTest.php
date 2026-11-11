<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Phone\PhoneNumber;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use Phil\PhoneBundle\PhilPhoneBundle;
use Phil\PhoneBundle\Tests\Functional\App\Entity\Shop;
use Phil\PhoneBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

#[CoversClass(PhilPhoneBundle::class)]
final class PhoneBundleTest extends KernelTestCase
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

    public function testANumberIsStoredInItsColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Shop(PhoneNumber::parse('+1 514 555 0123 poste 12')));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('+15145550123;ext=12', (string) $entityManager->getRepository(Shop::class)->findOneBy([])?->phone);
    }

    public function testTheConstraintSpeaksTheLocale(): void
    {
        $validator = $this->service('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $phone = PhoneNumber::parse('+15145550123');

        self::assertCount(0, $validator->validate(new Shop($phone, '514 555 0199')));
        self::assertSame('Ce numéro de téléphone n’est d’aucun des pays acceptés : CA, US.', $validator->validate(new Shop($phone, '+33 6 12 34 56 78'))->get(0)->getMessage());
        self::assertSame('Ce numéro de téléphone n’est pas d’un type accepté.', $validator->validate(new Shop($phone, '1 800 555 0199'))->get(0)->getMessage());
        self::assertSame('Cette valeur n’est pas un numéro de téléphone valide.', $validator->validate(new Shop($phone, 'nope'))->get(0)->getMessage());
    }

    public function testTheFieldAndTwigAreReady(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('phone', PhoneType::class);
        $form->submit('514 5551');

        self::assertFalse($form->isValid());
        self::assertSame(['Cette valeur n’est pas un numéro de téléphone valide.'], array_map(static fn (FormError|FormErrorIterator $error): string => $error->getMessage(), iterator_to_array($form->getErrors())));

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertSame('<a href="tel:+1-514-555-0123">(514) 555-0123</a>', $twig->createTemplate('<a href="{{ phone|phone_link }}">{{ phone|phone(\'national\') }}</a>')->render(['phone' => '514 555 0123']));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
