<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit\Infrastructure;

use Phil\Phone\PhoneNumber;
use Phil\PhoneBundle\Infrastructure\Form\PhoneNumberTransformer;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(PhoneType::class)]
#[CoversClass(PhoneNumberTransformer::class)]
final class PhoneTypeTest extends TestCase
{
    public function testANumberIsReadInTheDefaultRegion(): void
    {
        $form = $this->forms('CA')->create(PhoneType::class);
        $form->submit('(514) 555-0123');

        self::assertTrue($form->isValid());
        self::assertSame('+15145550123', (string) $form->getData());
        self::assertSame([TelType::class, ['autocomplete' => 'tel', 'maxlength' => 100], 'This value is not a valid phone number.', 'CA', null], [$form->getConfig()->getType()->getParent()?->getInnerType()::class, $form->getConfig()->getOption('attr'), $form->getConfig()->getOption('invalid_message'), $form->getConfig()->getOption('region'), $form->getConfig()->getOption('data_class')]);
    }

    public function testTheFieldsRegionWins(): void
    {
        $form = $this->forms('CA')->create(PhoneType::class, null, ['region' => 'FR']);
        $form->submit('06 12 34 56 78');

        self::assertSame('+33612345678', (string) $form->getData());
    }

    public function testWithoutRegionANumberNeedsItsCountryCode(): void
    {
        $form = $this->forms(null)->create(PhoneType::class);
        $form->submit('514 555 0123');

        self::assertFalse($form->isValid());
        self::assertNull($form->getData());
        self::assertNull($form->getConfig()->getOption('region'));
    }

    public function testANumberIsShownNationallyInItsRegionAndInternationallyElsewhere(): void
    {
        self::assertSame('(514) 555-0123', $this->forms('ca')->create(PhoneType::class, PhoneNumber::parse('+15145550123'))->createView()->vars['value']);
        self::assertSame('+33 6 12 34 56 78', $this->forms('CA')->create(PhoneType::class, PhoneNumber::parse('+33612345678'))->createView()->vars['value']);
        self::assertSame('+1 514-555-0123', $this->forms(null)->create(PhoneType::class, PhoneNumber::parse('+15145550123'))->createView()->vars['value']);

        $empty = $this->forms('CA')->create(PhoneType::class, null, ['required' => false]);
        $empty->submit('');
        self::assertTrue($empty->isValid());
        self::assertNull($empty->getData());
    }

    public function testTheTransformerRefusesWhatIsNoNumberAndWhatIsInNoPlan(): void
    {
        $transformer = new PhoneNumberTransformer('CA');

        self::assertSame('', $transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));

        try {
            $transformer->reverseTransform('1-800-FLOWERS');
            self::fail('Letters were read.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('"1-800-FLOWERS" is not a phone number.', $transformationFailedException->getMessage());
            self::assertNotNull($transformationFailedException->getPrevious());
        }

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('"+15145551" is in no numbering plan.');

        $transformer->reverseTransform('514 5551');
    }

    private function forms(?string $region): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new PhoneType($region))->getFormFactory();
    }
}
