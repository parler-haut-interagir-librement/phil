<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Locale;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementTransformer;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Throwable;

#[CoversClass(MeasurementType::class)]
#[CoversClass(MeasurementTransformer::class)]
final class MeasurementTypeTest extends TestCase
{
    private string $locale;

    private UnitRegistry $registry;

    private FormFactoryInterface $forms;

    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        $this->registry = UnitRegistry::standard();
        $type = new MeasurementType($this->registry, new ChannelConversions($this->registry, ['marketplace' => ['mass' => '[lb_av]', 'length' => '[in_i]']]), UnitFormatter::cldr());
        $this->forms = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([$type], []))->getFormFactory();
    }

    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testTheFieldsHaveNoLabelAndTheErrorsStayOnTheCompoundField(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['units' => ['kg', 'g']]);

        self::assertFalse($form->getConfig()->getOption('error_bubbling'));
        self::assertFalse($form->get('value')->getConfig()->getOption('label'));
        self::assertFalse($form->get('unit')->getConfig()->getOption('label'));
        self::assertFalse($form->get('unit')->getConfig()->getOption('choice_translation_domain'));
    }

    public function testAUnitOfferedTwiceIsOfferedOnceAndTheListIsKept(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['units' => ['kg', 'kg', 'g']]);

        self::assertSame(['kg', 'g'], $form->getConfig()->getOption('units'));
    }

    public function testTheLibrarysRefusalOfAUnitIsKeptAsThePrevious(): void
    {
        try {
            $this->forms->create(MeasurementType::class, null, ['units' => ['furlong']]);
            self::fail('An unknown unit is refused.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            $chain = [];

            for ($previous = $invalidOptionsException->getPrevious(); $previous instanceof Throwable; $previous = $previous->getPrevious()) {
                $chain[] = $previous::class;
            }

            self::assertContains(InvalidOptionsException::class, $chain);
            self::assertNotSame([], array_filter($chain, static fn (string $class): bool => is_subclass_of($class, MeasurementException::class)), implode(', ', $chain));
        }
    }

    public function testEveryUnitOfTheFamilyIsOffered(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['family' => 'mass']);
        $choices = $form->get('unit')->getConfig()->getOption('choices');

        self::assertIsArray($choices);
        self::assertSame(array_keys($this->registry->family('mass')->units), array_keys($choices));
        self::assertSame('kg', $form->get('unit')->getViewData());
        self::assertSame(['inputmode' => 'decimal'], $form->get('value')->getConfig()->getOption('attr'));
        self::assertSame('phil_measurement', $form->getConfig()->getType()->getBlockPrefix());

        $view = $form->createView();
        $choices = $view['unit']->vars['choices'];
        self::assertIsArray($choices);
        $labels = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $labels[] = $choice->label;
        }

        self::assertContains('lb', $labels);
        self::assertContains('kg', $labels);
    }

    public function testASubmittedMeasurementIsReadInTheLocale(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['units' => ['kg', 'g', 'g']]);
        $form->submit(['value' => '1 234,5', 'unit' => 'g']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('1234.5 g', (string) $form->getData());
        self::assertCount(2, (array) $form->get('unit')->getConfig()->getOption('choices'));
    }

    public function testOneUnitIsOfferedWithoutAChoice(): void
    {
        $form = $this->forms->create(MeasurementType::class, $this->registry->measurement('1', '[lb_av]'), ['unit' => 'kg', 'required' => false]);

        self::assertFalse($form->has('unit'));
        self::assertSame('0,45359237', $form->get('value')->getViewData());
        self::assertFalse($form->get('value')->getConfig()->getOption('required'));

        $form->submit(['value' => '2']);
        self::assertSame('2 kg', (string) $form->getData());
    }

    public function testAnEmptyFieldStartsOnTheUnitOfTheChannel(): void
    {
        self::assertSame('[lb_av]', $this->forms->create(MeasurementType::class, null, ['family' => 'mass', 'channel' => 'marketplace'])->get('unit')->getViewData());
        self::assertSame('g', $this->forms->create(MeasurementType::class, null, ['units' => ['g', 'kg'], 'channel' => 'marketplace'])->get('unit')->getViewData());
        self::assertSame('g', $this->forms->create(MeasurementType::class, null, ['units' => ['g', 'kg'], 'channel' => 'storefront'])->get('unit')->getViewData());
    }

    public function testAnUnreadableValueFailsTheForm(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['family' => 'mass']);
        $form->submit(['value' => 'douze', 'unit' => 'kg']);

        self::assertFalse($form->isSynchronized());
        self::assertSame('This value is not a valid measurement.', $form->getConfig()->getOption('invalid_message'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedOptions(): iterable
    {
        yield 'nothing offered' => [[]];
        yield 'empty units' => [['units' => []]];
        yield 'two families' => [['units' => ['kg', 'm']]];
        yield 'units outside the family' => [['family' => 'length', 'units' => ['kg']]];
        yield 'unknown unit' => [['unit' => 'furlong']];
        yield 'unknown family' => [['family' => 'luminosity']];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refusedOptions')]
    public function testItRefusesOptionsThatOfferNoUnitOfOneFamily(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->forms->create(MeasurementType::class, null, $options);
    }

    public function testAFixedUnitWinsOverTheList(): void
    {
        $form = $this->forms->create(MeasurementType::class, null, ['unit' => 'g', 'units' => ['kg', 'm'], 'family' => 'mass']);

        self::assertFalse($form->has('unit'));
        self::assertSame(['g'], $form->getConfig()->getOption('units'));
    }
}
