<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use Phil\GeoBundle\PhilGeoBundle;
use Phil\GeoBundle\Tests\Functional\App\Entity\Store;
use Phil\GeoBundle\Tests\Functional\App\NominatimResponses;
use Phil\GeoBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

#[CoversClass(PhilGeoBundle::class)]
final class GeoBundleTest extends KernelTestCase
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

    public function testAPositionIsStoredInTwoColumns(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Store('Plateau', Coordinates::of(45.5225, -73.5841)));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['id', 'name', 'position_latitude', 'position_longitude'], $entityManager->getClassMetadata(Store::class)->getColumnNames());
        $store = $entityManager->getRepository(Store::class)->findOneBy(['name' => 'Plateau']);
        self::assertNotNull($store);
        self::assertTrue($store->position->equals(Coordinates::of(45.5225, -73.5841)));
    }

    public function testTheFormAndTwigAreReady(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('position', CoordinatesType::class);
        $form->submit(['latitude' => '45,5225', 'longitude' => '-73,5841']);
        self::assertTrue($form->isValid());
        self::assertEquals(Coordinates::of(45.5225, -73.5841), $form->getData());

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('<li {{ geo_attributes(store, 2) }}>{{ geo_distance(here, store)|format_distance }}</li>')->render(['store' => Coordinates::of(45.5225, -73.5841), 'here' => Coordinates::of(45.5017, -73.5673)]);

        self::assertSame('<li data-phil--geo-bundle--distance-sort-target="item" data-latitude="45.52" data-longitude="-73.58">2,7 km</li>', $html);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }

    public function testAddressesAreGeocodedAndKept(): void
    {
        $geocoder = $this->service('test.geocoder');
        self::assertInstanceOf(Geocoder::class, $geocoder);
        $responses = $this->service(NominatimResponses::class);
        self::assertInstanceOf(NominatimResponses::class, $responses);
        self::getContainer()->get('cache.app')->clear();

        $places = $geocoder->geocode('4545 avenue Pierre-De Coubertin', 'fr');
        $geocoder->geocode('4545 avenue Pierre-De Coubertin', 'fr');

        self::assertSame(['45.558,-73.5517', 'Montréal', 'CA'], [$places[0]->coordinates->toString(), $places[0]->locality, $places[0]->countryCode]);
        self::assertCount(1, $responses->urls);
        self::assertStringStartsWith('https://nominatim.openstreetmap.org/search?q=4545', $responses->urls[0]);
    }
}
