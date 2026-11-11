<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\AddressBundle\Infrastructure\Form\AddressType;
use Phil\AddressBundle\PhilAddressBundle;
use Phil\AddressBundle\Tests\Functional\App\Entity\Shop;
use Phil\AddressBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

#[CoversClass(PhilAddressBundle::class)]
final class AddressBundleTest extends KernelTestCase
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

    public function testAnAddressIsStoredAndChecked(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $address = Address::of('CA', ['addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8']);

        $entityManager->persist(new Shop($address));
        $entityManager->flush();
        $entityManager->clear();

        $stored = $entityManager->getRepository(Shop::class)->findOneBy([]);
        self::assertTrue($address->equals($stored->address ?? Address::of('FR')));

        $validator = $this->service('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $violations = $validator->validate(new Shop($address->with(AddressField::PostalCode, '75001')));
        self::assertSame(['address.postalCode', 'Ce code postal n’est pas de ce pays ou de cette région.'], [$violations->get(0)->getPropertyPath(), $violations->get(0)->getMessage()]);
    }

    public function testTheFormSpeaksTheLocaleAndCarriesItsController(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $forms->createNamed('address', AddressType::class)->createView()]);

        self::assertStringContainsString('data-controller="phil--address-bundle--address"', $html);
        self::assertStringContainsString('data-phil--address-bundle--address-url-value="/address-format/__COUNTRY__?locale=fr_CA"', $html);
        self::assertStringContainsString('>Province</label>', $html);
        self::assertStringContainsString('>Québec (QC)</option>', $html);
        self::assertStringContainsString('data-field="sortingCode" hidden="hidden"', $html);

        $form = $forms->createNamed('address', AddressType::class);
        $form->submit(['country' => 'CA', 'addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'QC']);
        self::assertSame('Cette valeur est requise dans une adresse de ce pays.', $form->get('postalCode')->getErrors()->current()->getMessage());
        self::assertSame("1\nMontréal QC", $twig->createTemplate('{{ address|address }}')->render(['address' => Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'Québec'])]));
    }

    public function testTheFormatEndpointAnswersInTheRequestsLanguage(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $request = Request::create('/address-format/US');
        $request->attributes->set('_locale', 'fr');

        $format = json_decode((string) $kernel->handle($request)->getContent(), true);

        self::assertIsArray($format);
        self::assertIsArray($format['fields'] ?? null);
        self::assertIsArray($format['subdivisions'] ?? null);
        self::assertContains(['name' => 'administrativeArea', 'label' => 'État', 'required' => true], $format['fields']);
        self::assertContains(['code' => 'TX', 'name' => 'Texas (TX)'], $format['subdivisions']);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
