<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit\Infrastructure;

use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesTransformer;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(CoordinatesType::class)]
#[CoversClass(CoordinatesTransformer::class)]
final class CoordinatesTypeTest extends TestCase
{
    public function testAPositionIsEnteredAsTwoFields(): void
    {
        $form = $this->forms()->create(CoordinatesType::class);
        $form->submit(['latitude' => '45.5017', 'longitude' => '-73.5673']);

        self::assertTrue($form->isValid());
        self::assertEquals(Coordinates::of(45.5017, -73.5673), $form->getData());

        foreach (['latitude', 'longitude'] as $field) {
            $options = $form->get($field)->getConfig()->getOptions();
            self::assertSame([false, 7, true, ['step' => 'any', 'inputmode' => 'decimal'], true], [$options['label'], $options['scale'], $options['html5'], $options['attr'], $options['required']]);
        }

        self::assertSame([true, false, null, 'This value is not a valid position.'], [$form->getConfig()->getCompound(), $form->getConfig()->getErrorBubbling(), $form->getConfig()->getDataClass(), $form->getConfig()->getOption('invalid_message')]);
    }

    public function testAPositionIsShown(): void
    {
        $view = $this->forms()->create(CoordinatesType::class, Coordinates::of(45.5017, -73.5673))->createView();

        self::assertSame(['45.5017000', '-73.5673000'], [$view['latitude']->vars['value'], $view['longitude']->vars['value']]);
        self::assertSame(['', ''], [$this->forms()->create(CoordinatesType::class)->createView()['latitude']->vars['value'], $this->forms()->create(CoordinatesType::class)->createView()['longitude']->vars['value']]);
    }

    public function testAnOptionalPositionMayStayEmpty(): void
    {
        $form = $this->forms()->create(CoordinatesType::class, null, ['required' => false]);
        $form->submit(['latitude' => '', 'longitude' => '']);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
        self::assertFalse($form->get('latitude')->getConfig()->getOption('required'));
    }

    public function testHalfAPositionOrOneOffTheEarthIsInvalid(): void
    {
        foreach ([['latitude' => '45.5', 'longitude' => ''], ['latitude' => '', 'longitude' => '-73.5'], ['latitude' => '91', 'longitude' => '0']] as $submitted) {
            $form = $this->forms()->create(CoordinatesType::class);
            $form->submit($submitted);

            self::assertFalse($form->isValid());
            self::assertNull($form->getData());
        }
    }

    public function testTheTransformerSaysWhy(): void
    {
        $transformer = new CoordinatesTransformer();

        self::assertSame(['latitude' => null, 'longitude' => null], $transformer->transform(null));
        self::assertNull($transformer->reverseTransform([]));

        foreach ([[['latitude' => 1.0], 'A position has both a latitude and a longitude.'], [['longitude' => 1.0], 'A position has both a latitude and a longitude.'], [['latitude' => 91.0, 'longitude' => 0.0], 'A latitude is -90 to 90 degrees; 91 is not.']] as [$value, $message]) {
            try {
                $transformer->reverseTransform($value);
                self::fail($message);
            } catch (TransformationFailedException $failed) {
                self::assertSame($message, $failed->getMessage());
                self::assertSame(0, $failed->getCode());
            }
        }
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new CoordinatesType())->getFormFactory();
    }
}
