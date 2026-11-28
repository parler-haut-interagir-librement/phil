<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementType;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementRange;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementUnit;
use Phil\MeasurementBundle\PhilMeasurementBundle;
use Phil\MeasurementBundle\Tests\Functional\App\Entity\Parcel;
use Phil\MeasurementBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * The bundle in a real kernel, in `fr_CA`: a weight entered in a form, validated with translated messages, written by
 * Twig, and stored in one column on SQLite with the application's own unit.
 */
#[CoversClass(PhilMeasurementBundle::class)]
final class MeasurementBundleTest extends KernelTestCase
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

    public function testAWeightIsEnteredInTheLocaleAndTheUnitOfTheChannel(): void
    {
        $forms = self::getContainer()->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);

        $form = $forms->createNamed('weight', MeasurementType::class, null, ['family' => 'mass', 'channel' => 'marketplace']);
        self::assertSame('[lb_av]', $form->get('unit')->getViewData());

        $form->submit(['value' => '12,5', 'unit' => '[gr]']);
        self::assertTrue($form->isValid());
        self::assertSame('12.5 [gr]', (string) $form->getData());
    }

    public function testAWeightOutOfRangeIsRefusedInFrench(): void
    {
        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        $violations = $validator->validate($this->registry()->measurement('70', '[lb_av]'), [new MeasurementRange(max: '30 kg'), new MeasurementUnit(family: 'mass', units: ['kg'])]);

        self::assertSame(
            ['Cette mesure doit être d’au plus 30 kg.', 'L’unité [lb_av] n’est pas acceptée.'],
            array_map(static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(), iterator_to_array($violations)),
        );
    }

    public function testTwigWritesAWeightInTheLocale(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $template = $twig->createTemplate("{{ weight|format_measurement('long') }} | {{ weight|measurement_in_channel('marketplace')|format_measurement }} | {{ height|preferred_units('person-height', 'US', 0)|format_measurement('long', 0, 'en') }} | {{ none|format_measurement }}");

        self::assertSame(
            "12,5\u{a0}kilogrammes | 27,56\u{a0}lb | 5 feet, 10 inches | ",
            $template->render(['weight' => $this->registry()->measurement('12.5', 'kg'), 'height' => $this->registry()->measurement('178', 'cm'), 'none' => null]),
        );
    }

    public function testAWeightIsStoredInOneColumnAndReadBack(): void
    {
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $manager->persist(new Parcel($this->registry()->measurement('7000', '[gr]'), $this->registry()->measurement('30.5', 'cm')));
        $manager->persist(new Parcel($this->registry()->measurement('1', 'kg')));
        $manager->flush();
        $manager->clear();

        $rows = $manager->getConnection()->fetchAllAssociative('SELECT weight, length FROM parcel ORDER BY id');
        self::assertSame([['weight' => '7000 [gr]', 'length' => '30.5 cm'], ['weight' => '1 kg', 'length' => null]], $rows);

        $parcels = $manager->getRepository(Parcel::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $parcels);
        self::assertInstanceOf(Measurement::class, $parcels[0]->weight);
        self::assertSame('1', $parcels[0]->weight->convertTo($this->registry()->unit('[lb_av]'))->toDecimal(6));
        self::assertNull($parcels[1]->length);
        self::assertStringContainsString('VARCHAR(40)', implode(' ', new SchemaTool($manager)->getCreateSchemaSql($manager->getMetadataFactory()->getAllMetadata())));
    }

    private function registry(): UnitRegistry
    {
        $registry = self::getContainer()->get(UnitRegistry::class);
        self::assertInstanceOf(UnitRegistry::class, $registry);

        return $registry;
    }
}
